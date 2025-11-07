<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader;

use Exception;
use Zolinga\Commons\Downloader\Exception\BadRequestException;
use Zolinga\Commons\Downloader\Exception\GotNothingException;
use Zolinga\Commons\Downloader\Exception\HttpErrorResponseException;
use Zolinga\Commons\Downloader\Exception\PaymentRequiredException;
use Zolinga\Commons\Downloader\Exception\RequestTimeoutException;
use Zolinga\System\Events\ServiceInterface;
use Zolinga\Commons\Downloader\Exception\SslException;
use Zolinga\Commons\Downloader\Exception\TimeoutException;
use Zolinga\Commons\Downloader\Exception\UnauthorizedException;

// Import the manager classes
use Zolinga\Commons\Downloader\CookieManager;
use Zolinga\Commons\Downloader\Exception\CurlException;
use Zolinga\Commons\Downloader\Exception\ForbiddenException;
use Zolinga\Commons\Downloader\Exception\NotFoundException;
use Zolinga\Commons\Downloader\UserAgentManager;

/**
 * Downloader Service API. Uses CURL to download files and send JSON requests.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-06-13
 */
class DownloaderService implements ServiceInterface
{
    /**
     * Keep the connection alive after the request. Do not close it.
     * You need to explicitly call $this->closeConnection() to close it.
     */
    public const OPT_KEEP_ALIVE = 1;

    /**
     * If the request fails, throw an exception and do not try to recover.
     */
    public const FAIL_FAST = 2;

    /**
     * When self::OPT_KEEP_ALIVE is set this will hold the persistent CURL handle
     * and each subsequent request with self::OPT_KEEP_ALIVE will reuse this handle.
     * 
     * Call $this->closeConnection() to close this handle.
     * 
     * Tested: One handle can keep multiple connectins open to multiple domains. 
     *
     * @var \CurlHandle|null
     */
    private ?\CurlHandle $curlKeepAliveHandler = null;

    private const UNIQUE_HTTP_HEADERS = [
        'Accept',
        'Accept-Charset',
        'Accept-Encoding',
        'Accept-Language',
        'Accept-Datetime',
        'Authorization',
        'Cache-Control',
        'Connection',
        'Content-Length',
        'Content-MD5',
        'Content-Type',
        'Date',
        'Expect',
        'Forwarded',
        'From',
        'Host',
        'If-Match',
        'If-Modified-Since',
        'If-None-Match',
        'If-Range',
        'If-Unmodified-Since',
        'Max-Forwards',
        'Origin',
        'Pragma',
        'Proxy-Authorization',
        'Range',
        'Referer',
        'TE',
        'User-Agent',
        'Upgrade',
        'Via',
        'Warning',
        'Sec-Fetch-Dest',
        'Sec-Fetch-Mode',
        'Sec-Fetch-Site',
        'Sec-Fetch-User',
        'Accept-Ranges',
        'Age',
        'Allow',
        'Content-Disposition',
        'Content-Encoding',
        'Content-Language',
        'Content-Location',
        'Content-Range',
        'Sec-Ch-Ua',
        'Sec-Ch-Ua-Mobile',
        'Sec-Ch-Ua-Platform',
    ];
    protected readonly string $downloaderName;
    protected readonly string $cookieJarFileName; // Keep for direct cURL usage
    public readonly CookieManager $cookieManager;
    public readonly UserAgentManager $uaManager;

    private array $curlOpts = [
        CURLOPT_AUTOREFERER => 1,
        CURLOPT_COOKIESESSION => 1,
        CURLOPT_DNS_USE_GLOBAL_CACHE => 0,
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_TIMEOUT_MS => 10000,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS, // Ensure HTTP/2 support
        CURLOPT_HTTPHEADER => [
            // 'Connection: keep-alive', - not automatically to have rotating proxies
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'DNT: 1',
        ],
        CURLOPT_FORBID_REUSE => 0, // 1: Do not reuse the connection.
        CURLOPT_HEADER => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FAILONERROR => 1,
        CURLOPT_VERBOSE => 0,
    ];

    public function __construct(string $downloaderName = 'downloader')
    {
        global $api;

        $this->downloaderName = $downloaderName;
        $this->cookieJarFileName = $api->fs->toPath('private://zolinga-commons/cookies/' . basename($downloaderName) . '.txt');

        // Initialize the managers
        $this->cookieManager = new CookieManager($this->cookieJarFileName);
        $this->uaManager = new UserAgentManager();

        $this->randomizeUserAgent();
    }

    /**
     * Return new Downloader object instance.
     * 
     * Good if you need it to configure differently then the standard service.
     * 
     * Example:
     * $downloader = $api->downloader::createInstance('downloader2');
     * $downloader->setOpts([CURLOPT_PROXY => 'http://proxy:port']);
     * $downloader->download('https://example.com');
     * 
     *
     * 
     * @param string $downloaderName
     * @return static New instance of $this object.
     */
    public static function createInstance(string $downloaderName = 'downloader'): static
    {
        $class = static::class;
        return new $class($downloaderName);
    }

    /**
     * Set additional CURL default options for this object.
     * 
     * CURLOPT_HTTPHEADER is smart merged with the existing headers.
     * 
     * @param array $opts
     * @return array The updated CURL options.
     */
    public function setOpts(array $opts): array
    {
        // Smart mearge headers
        if (isset($opts[CURLOPT_HTTPHEADER])) {
            $opts[CURLOPT_HTTPHEADER] = $this->mergeHeaders($this->curlOpts[CURLOPT_HTTPHEADER], $opts[CURLOPT_HTTPHEADER]);
        }

        $this->curlOpts = array_replace($this->curlOpts, $opts);
        return $this->curlOpts;
    }

    /**
     * Smart merge headers from multiple sources.
     * 
     * Example:
     * 
     * ```php
     * $headers = $this->mergeHeaders(['Header: value1', 'Header: value2'], ['Header: value3']);
     * ```
     * 
     * @param mixed ...$list
     * @return array
     */
    private function mergeHeaders(...$list): array
    {
        $headers = array_merge(...$list);

        $parsed = [];
        foreach ($headers as $header) {
            [$key, $value] = explode(':', $header, 2);
            $key = ucwords(strtolower($key), '-'); // 'Content-Type' => 'content-type
            if (in_array($key, self::UNIQUE_HTTP_HEADERS)) {
                $parsed[$key] = [$value];
            } else {
                $parsed[$key] = array_merge($parsed[$key] ?? [], [$value]);
            }
        }

        $unparsed = [];
        foreach ($parsed as $key => $values) {
            foreach (array_unique($values) as $value) {
                $unparsed[] = "$key: $value";
            }
        }

        return $unparsed;
    }


    /**
     * Randomizes the user agent string used for requests.
     */
    public function randomizeUserAgent(): void
    {
        $userAgentData = $this->uaManager->getRandomUserAgentData();

        // Merge headers obtained from the UserAgentManager
        $this->setOpts([CURLOPT_USERAGENT => $userAgentData['userAgent']]);
        $headerLines = array_map(fn($k, $v) => "$k: $v", array_keys($userAgentData['headers']), $userAgentData['headers']);
        // Ensure we merge with existing headers, especially those not managed by ua-headers.json
        $this->curlOpts[CURLOPT_HTTPHEADER] = $this->mergeHeaders($this->curlOpts[CURLOPT_HTTPHEADER] ?? [], $headerLines);
    }

    /**
     * Set the user agent string used for requests.
     *
     * @param string $ua
     * @return void
     */
    public function setUserAgent(string $ua): void
    {
        $this->setOpts([CURLOPT_USERAGENT => $ua]);
    }

    /**
     * Sends a JSON request to the specified URL using the specified method and payload.
     * 
     * Example:
     * 
     * ```php
     * $resp = $this->jsonRequest('https://example.com/api', ['key' => 'value'], 'POST');
     * ```
     * Internally calls `$this->download()` so it uses the same CURL options and throws the same exceptions.
     * 
     * @param string $url The URL to send the request to.
     * @param array|null $payload The payload to include in the request.
     * @param string $method The HTTP method to use for the request (default: "GET").
     * @param array $curlOpts Additional cURL options to include in the request (default: []).
     * @param int $downloaderOpts Additional downloader options to include in the request. Cupported options are: DownloaderService::OPT_KEEP_ALIVE (default: 0).
     * @return array The JSON response from the server.
     * @throws Exception If the request fails or if the response cannot be decoded.
     */
    public function jsonRequest(string $url, ?array $payload = null, string $method = "GET", array $curlOpts = [], int $downloaderOpts = 0): array
    {
        global $api;

        $opts = array_replace(
            $curlOpts,
            [
                CURLOPT_HTTPHEADER => array_merge($curlOpts[CURLOPT_HTTPHEADER] ?? [], ['Content-Type: application/json; charset=utf-8'])
            ]
        );
        switch ($method) {
            case 'GET':
                $url .= '?' . http_build_query($payload);
                $opts[CURLOPT_CUSTOMREQUEST] = 'GET';
                break;
            case 'POST':
            case 'PUT':
            case 'DELETE':
                $opts[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
                $opts[CURLOPT_POSTFIELDS] = $payload ? json_encode($payload) : null;
                break;
            default:
                throw new \Exception("Unsupported method $method");
        }

        $text = $this->download($url, false, $opts, $downloaderOpts)
            or throw new \Exception($this::class . ": Failed to download JSON response from $url");
        $resp = json_decode($text, true)
            or throw new \JsonException($this::class . ": Failed to decode JSON response from $url: " . json_encode(substr($text, 0, 256)) . "...");

        return $resp;
    }

    /**
     * Download a remote resource using CURL.
     * 
     * By default it stores cookies persistently into $this->cookieJarFileName 
     * stored in private://ipdefender-daq/cookies/ directory. It reuses cookies
     * for subsequent requests. It also follows redirects by default.
     *
     * @throws HttpErrorResponseException If cURL returns CURLE_HTTP_RETURNED_ERROR, the HTTP status code is not 200 OK. Exception's code is the HTTP status code.
     *          e.g. 404 Not Found, 500 Internal Server Error.
     * @throws TimeoutException If cURL returns CURLE_OPERATION_TIMEOUTED.
     * @throws SslException If cURL returns an SSL-related error (e.g., CURLE_SSL_CACERT_BADFILE, CURLE_SSL_CERTPROBLEM).
     * @throws GotNothingException If cURL returns CURLE_GOT_NOTHING.
     * @throws Exception For any other cURL error.
     * 
     * Note: Do not forget to set curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); if you want to get the content as return value.
     * 
     * @param string $url
     * @param array<mixed> $curlOpts Additional CURL options. E.g. [CURLOPT_PROXY => 'http://proxy:port']
     * @param int $downloaderOpts Additional downloader options. E.g. DownloaderService::OPT_KEEP_ALIVE
     * @param \CurlHandle|null $curlHandle Optional existing cURL handle to reuse.
     * @return \CurlHandle
     */
    public function prepareCurlHandle(string $url, array $curlOpts = [], int $downloaderOpts = 0, null|\CurlHandle $curlHandle = null): \CurlHandle
    {
        // Remove hash from URL
        $url = preg_replace("@#.*$@", "", $url);
        $keepAlive = $downloaderOpts & self::OPT_KEEP_ALIVE;
        $failFast = $downloaderOpts & self::FAIL_FAST;

        if ($keepAlive) {
            $curlOpts = array_replace($curlOpts, [CURLOPT_FORBID_REUSE => 0]);
        }

        if ($failFast) {
            $curlOpts = array_replace($curlOpts, [CURLOPT_FAILONERROR => 1]);
        }

        $ch = $curlHandle ?? ($keepAlive && $this->curlKeepAliveHandler ? $this->curlKeepAliveHandler : curl_init())
            or throw new CurlException('Failed to initialize CURL with URL ' . $url);

        $curlOpts = array_replace(
            $this->curlOpts,
            [
                CURLOPT_REFERER => $curlOpts[CURLOPT_REFERER] ?? "https://www.google.com/?source=osdd&sl=auto&tl=auto&text=" . parse_url($url, PHP_URL_HOST) . "&op=translate",
                CURLOPT_COOKIEFILE => $this->cookieJarFileName, // Read init cookies from here. By passing the empty string ("") to this option, you enable the cookie engine without reading any initial cookies.
                CURLOPT_COOKIEJAR => $this->cookieJarFileName, // Write cookies here.
                CURLOPT_URL => $url,
                CURLOPT_ENCODING => "", // Enables gzip/deflate automatically (reuters detects compression support otherwise it sends 401)
                CURLOPT_FAILONERROR => false, // otherwise error 400 does not return the content and throws an error.
            ],
            $curlOpts
        );

        /** @var \CurlHandle $ch */
        foreach ($curlOpts as $opt => $val) {
            curl_setopt($ch, $opt, $val);
        }

        return $ch;
    }

    /**
     * Download a remote resource using CURL.
     * 
     * By default it stores cookies persistently into $this->cookieJarFileName 
     * stored in private://ipdefender-daq/cookies/ directory. It reuses cookies
     * for subsequent requests. It also follows redirects by default.
     *
     * @throws HttpErrorResponseException If cURL returns CURLE_HTTP_RETURNED_ERROR, the HTTP status code is not 200 OK. Exception's code is the HTTP status code.
     *          e.g. 404 Not Found, 500 Internal Server Error.
     * @throws TimeoutException If cURL returns CURLE_OPERATION_TIMEOUTED.
     * @throws SslException If cURL returns an SSL-related error (e.g., CURLE_SSL_CACERT_BADFILE, CURLE_SSL_CERTPROBLEM).
     * @throws GotNothingException If cURL returns CURLE_GOT_NOTHING.
     * @throws Exception For any other cURL error.
     *
     * @param string $url
     * @param false|boolean $outFile false to return the content, string to save to file.
     * @param array<mixed> $curlOpts Additional CURL options. E.g. [CURLOPT_PROXY => 'http://proxy:port']
     * @param int $downloaderOpts Additional downloader options. E.g. DownloaderService::OPT_KEEP_ALIVE
     * @return boolean|string Content of the resource if $outFile is false, otherwise true on success.
     */
    public function download(string $url, false|string $outFile = false, array $curlOpts = [], int $downloaderOpts = 0): bool|string
    {
        global $api;

        // Remove hash from URL
        $start = microtime(true);
        $file = false;
        $result = false;
        $keepAlive = $downloaderOpts & self::OPT_KEEP_ALIVE;


        try {
            $ch = $this->prepareCurlHandle($url, curlOpts: $curlOpts, downloaderOpts: $downloaderOpts);

            if (is_string($outFile)) {
                $file = $this->curlPrepareOutputFile($outFile);
                curl_setopt($ch, CURLOPT_FILE, $file);
            } else { // important: you cannot set both CURLOPT_FILE and CURLOPT_RETURNTRANSFER to any value, 
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            }

            $result = curl_exec($ch);
        } catch (\Throwable $e) {
            $api->log->error($this->downloaderName, "CURL: Failed to download $url: {$e->getCode()} {$e->getMessage()}", [
                "url" => $url,
                'error' => $e->getMessage(),
                'errno' => $e->getCode(),
                'info' => curl_getinfo($ch),
                'cookies' => $this->cookieManager->getCookies(parse_url($url, PHP_URL_HOST)),
                'opts' => array_filter($curlOpts, fn($k) => $k !== CURLOPT_FILE) // file is resource - not serializable
            ]);
            throw $e;
        } finally {
            if ($keepAlive && !$this->curlKeepAliveHandler) {
                $api->log->info($this->downloaderName, "Keeping connection alive...");
                $this->curlKeepAliveHandler = $ch;
            } elseif (!$keepAlive) {
                curl_close($ch);
            }
            if ($file) fclose($file);
        }

        // Checks the result and throws exceptions if necessary
        $this->curlCheckResult($url, $result, $outFile, $curlOpts, $downloaderOpts, $ch, $start);

        return $result;
    }

    /**
     * When you use self::OPT_KEEP_ALIVE wity the request you need to close the connection manually.
     *
     * @return void
     */
    public function closeConnection(): void
    {
        global $api;

        if ($this->curlKeepAliveHandler) {
            $api->log->info($this->downloaderName, "Closing keep-alive connection...");
            curl_close($this->curlKeepAliveHandler);
            $this->curlKeepAliveHandler = null;
        }
    }

   /**
     * Checks the result of a cURL request, logs the outcome, and throws specific exceptions on failure.
     *
     * This method analyzes the cURL execution result. If an error occurred, it logs detailed
     * error information and throws an appropriate exception based on the cURL error number.
     * If the download was successful, it logs success information including the downloaded size
     * and time taken.
     *
     * @param string $url The URL that was attempted to be downloaded.
     * @param bool|string $result The result of the curl_exec() call. False on failure, or the content if CURLOPT_RETURNTRANSFER was true.
     * @param mixed $outFile The file path where the content was saved (if applicable), or the downloaded content itself.
     * @param array $curlOpts The cURL options used for the request.
     * @param int $downloaderOpts The downloader specific options (e.g., self::OPT_KEEP_ALIVE).
     * @param \CurlHandle $ch The cURL handle used for the request.
     * @param float $start The microtime timestamp taken before starting the cURL request.
     *
     * @throws HttpErrorResponseException If cURL returns CURLE_HTTP_RETURNED_ERROR, the HTTP status code is not 200 OK. Exception's code is the HTTP status code.
     * @throws TimeoutException If cURL returns CURLE_OPERATION_TIMEOUTED.
     * @throws SslException If cURL returns an SSL-related error (e.g., CURLE_SSL_CACERT_BADFILE, CURLE_SSL_CERTPROBLEM).
     * @throws GotNothingException If cURL returns CURLE_GOT_NOTHING.
     * @throws Exception For any other cURL error.
     *
     * @return void
     */
    private function curlCheckResult(string $url, bool | string $result, mixed $outFile, array $curlOpts, int $downloaderOpts, \CurlHandle $ch, float $start)
    {
        global $api;

        $httpStatusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errMsg = curl_error($ch);
        $errNo = curl_errno($ch) ?: ($httpStatusCode >= 400 ? CURLE_HTTP_RETURNED_ERROR : 0);
        $elapsed = round(microtime(true) - $start, 2) . 's';
        $keepAliveText = $downloaderOpts & self::OPT_KEEP_ALIVE ? ' (keep-alive)' : '';

        if (!$result || $errNo || $httpStatusCode >= 400) {
            $api->log->error($this->downloaderName, "CURL: Failed to download $url$keepAliveText ($errNo $errMsg): " . json_encode($result), [
                "url" => $url,
                'error' => $errMsg,
                'errno' => $errNo,
                'httpStatusCode' => $httpStatusCode,
                'info' => curl_getinfo($ch),
                'cookies' => $this->cookieManager->getCookies(parse_url($url, PHP_URL_HOST)),
                'opts' => array_filter($curlOpts, fn($k) => $k !== CURLOPT_FILE), // file is resource - not serializable
                'downloaderOpts' => $downloaderOpts,
            ]);

            // See https://curl.se/libcurl/c/libcurl-errors.html
            switch ($errNo) {
                case CURLE_HTTP_RETURNED_ERROR: // error 22
                    switch ($httpStatusCode) 
                    {
                        case 400:
                            throw new BadRequestException("Bad request $url$keepAliveText: $errNo $errMsg (total time $elapsed)", $errNo, null, $result ?: null);
                        case 401:
                            throw new UnauthorizedException("Unauthorized $url$keepAliveText: $errNo $errMsg (total time $elapsed)", $errNo, null, $result ?: null);
                        case 402:
                            throw new PaymentRequiredException("Payment required $url$keepAliveText: $errNo $errMsg (total time $elapsed)", $errNo, null, $result ?: null);
                        case 403:
                            throw new ForbiddenException("Forbidden $url$keepAliveText: $errNo $errMsg (total time $elapsed)", $errNo, null, $result ?: null);
                        case 404:
                            throw new NotFoundException("Not found $url$keepAliveText: $errNo $errMsg (total time $elapsed)", $errNo, null, $result ?: null);
                        case 408:
                            throw new RequestTimeoutException("Request timeout $url$keepAliveText: $errNo $errMsg (total time $elapsed)", $errNo, null, $result ?: null);
                        default:
                            throw new HttpErrorResponseException($errMsg, $httpStatusCode, null, $result ?: null);
                    };
                case CURLE_OPERATION_TIMEOUTED: // error 28
                    throw new TimeoutException("Timeout downloading $url$keepAliveText (total time $elapsed)", $errNo);
                case CURLE_SSL_CACERT_BADFILE: // error 77
                case CURLE_SSL_CERTPROBLEM: // error 58 
                case CURLE_SSL_CIPHER: // error 59
                case CURLE_SSL_CONNECT_ERROR: // error 35
                case CURLE_SSL_ENGINE_NOTFOUND: // error 53
                case CURLE_SSL_ENGINE_SETFAILED: // error 54
                case CURLE_SSL_PINNEDPUBKEYNOTMATCH: // error 60
                    throw new SslException("SSL error downloading $url$keepAliveText: $errNo $errMsg (total time $elapsed)", $errNo);
                case CURLE_GOT_NOTHING: // error 52
                    throw new GotNothingException("Empty reply downloading $url$keepAliveText: $errNo $errMsg (total time $elapsed)", $errNo);
                default:
                    throw new Exception("Failed to download $url$keepAliveText: $errNo $errMsg (total time $elapsed)", $errNo);
            }
        } else {
            $size = is_string($outFile) ? filesize($outFile) : strlen($result);
            $sizeHuman = $api->convert->memoryUnits($size, "MiB", 3) . ' MiB';
            $api->log->info($this->downloaderName, "CURL: Downloaded $url$keepAliveText ($sizeHuman, total time $elapsed)", [
                "url" => $url,
                "error" => $errMsg,
                "errno" => $errNo,
                'info' => curl_getinfo($ch),
                'size' => $size,
                'elapsed' => $elapsed,
                'downloaderOpts' => $downloaderOpts,
            ]);
        }
    }

    private function curlPrepareOutputFile(string $outFile)
    {
        $dir = dirname($outFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $file = fopen($outFile, 'w+');

        if (!$file) {
            throw new Exception("Failed to open file $outFile for writing");
        }

        return $file;
    }

    /**
     * Flushes all cookies stored in the cookie jar.
     */
    public function flushCookies(): void
    {
        global $api;

        $api->log->info($this->downloaderName, "Removing all cookies...");
        $this->cookieManager->flushCookies();
    }


    /**
     * Return all cookies stored in the cookie jar.
     *
     * @param string|null $domain if null return all cookies, otherwise only cookies for the specified domain.
     * @param bool $full If false return only values of the cookies oterwise return the whole cookie array
     * @return array Array of cookies.
     */
    public function getCookies(?string $domain = null, bool $full = false): array
    {
        return $this->cookieManager->getCookies($domain, $full);
    }


    /**
     * TOR service can be very slow. Set the default timeout.
     *
     * @param integer $timeout Timeout in seconds. Default is 60. This is a total timeout for the whole request including connection, transfer, etc.
     * @param integer $connectionTimeout Connection timeout in seconds. Default is 15. Connection timeout + transfer (whole thing) must always be less than the $timeout.
     * @return void
     */
    public function setTimeout(int $timeout = 60, int $connectionTimeout = 10): void
    {
        global $api;

        $api->log->info($this->downloaderName, "Setting default timeout to $timeout seconds and connection timeout to $connectionTimeout seconds.");
        $this->setOpts([
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_TIMEOUT_MS => $timeout * 1000,
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME => $connectionTimeout,
            CURLOPT_CONNECTTIMEOUT => $connectionTimeout,
            CURLOPT_CONNECTTIMEOUT_MS => $connectionTimeout * 1000,
        ]);
    }

    /**
     * Set the proxy for the downloader.
     * 
     * @param string $host
     * @param integer $port
     * @param integer $type CURLPROXY_HTTP, CURLPROXY_SOCKS4, CURLPROXY_SOCKS5, CURLPROXY_SOCKS4A, CURLPROXY_SOCKS5_HOSTNAME
     * @return void
     */
    public function setProxy(string $host, int $port, int $type = CURLPROXY_SOCKS5): void
    {
        $this->setOpts([
            CURLOPT_PROXY => "$host:$port",
            CURLOPT_PROXYTYPE => $type,
        ]);
    }
}
