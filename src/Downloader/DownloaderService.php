<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader;

use Exception;
use Zolinga\Commons\Downloader\Exception\GotNothingException;
use Zolinga\System\Events\ServiceInterface;
use Zolinga\Commons\Downloader\Exception\SslException;
use Zolinga\Commons\Downloader\Exception\TimeoutException;

/**
 * Downloader Service API. Uses CURL to download files and send JSON requests.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-06-13
 */
class DownloaderService implements ServiceInterface
{
    protected readonly string $logPrefix;
    protected readonly string $cookieJarFileName;
    private const DEFAULT_CURL_OPTS  = [
        CURLOPT_AUTOREFERER => 1,
        CURLOPT_COOKIESESSION => 1,
        CURLOPT_DNS_USE_GLOBAL_CACHE => 0,
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_TIMEOUT_MS => 10000,
        CURLOPT_HTTPHEADER => [],
        // CURLOPT_COOKIE => '', // Is used when you want to specify the exact contents of a cookie header to send to the server.
        CURLOPT_COOKIESESSION => 0, // 1: Do not use cookies from the previous session.
        //CURLOPT_HTTPPROXYTUNNEL => true,
        //CURLOPT_PROXY
        //CURLOPT_PROXYPORT
        //CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
        //CURLOPT_RESUME_FROM => 0,
        //CURLOPT_COOKIE => 'test=1;test=2',
        CURLOPT_HEADER => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FAILONERROR => 1,
        // CURLOPT_TIMECONDITION => CURL_TIMECOND_IFMODSINCE,
        // CURLOPT_TIMEVALUE => 0,
        // CURLOPT_FILETIME => true, // return file time of remote resource
        CURLOPT_VERBOSE => 0,
        // CURLOPT_STDERR => fopen('php://temp', 'w+'),
        // CURLOPT_USERAGENT => 'Mozilla/5.0 (iPad; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
    ];
    private readonly array $uaStrings;

    public function __construct(string $cookieJarName = 'downloader')
    {
        global $api;

        $this->uaStrings = json_decode(file_get_contents('module://zolinga-commons/data/ua-strings.json'), true)
            or throw new Exception("Failed to load user agent strings from module://zolinga-commons/data/ua-strings.json");

        $this->logPrefix = $cookieJarName;
        $this->cookieJarFileName = $api->fs->toPath('private://ipdefender-daq/cookies/' . $cookieJarName . '.txt');
        $this->initCookieJar();
    }


    public function __get(string $name): mixed
    {
        switch ($name) {
            default:
                throw new Exception("Property $name not found in " . static::class);
        }
    }

    private function initCookieJar()
    {
        if (!is_dir(dirname($this->cookieJarFileName))) {
            mkdir(dirname($this->cookieJarFileName), 0777, true);
        }

        touch($this->cookieJarFileName)
            or throw new Exception("Failed to create cookie jar file $this->cookieJarFileName");
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
     * @return array The JSON response from the server.
     * @throws Exception If the request fails or if the response cannot be decoded.
     */
    public function jsonRequest(string $url, ?array $payload = null, string $method = "GET", array $curlOpts = []): array
    {
        global $api;

        $opts = array_replace(
            $curlOpts,
            [
                CURLOPT_HTTPHEADER => array_replace(
                    $curlOpts[CURLOPT_HTTPHEADER] ?? [],
                    ['Content-Type: application/json; charset=utf-8'],
                )
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

        $text = $this->download($url, false, $opts)
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
     * @throws TimeoutException If the download times out.
     * @throws Exception If the download fails.
     * 
     * @param string $url
     * @param false|boolean $outFile false to return the content, string to save to file.
     * @param array<mixed> $curlOpts Additional CURL options. E.g. [CURLOPT_PROXY => 'http://proxy:port']
     * @return boolean|string Content of the resource if $outFile is false, otherwise true on success.
     */
    public function download(string $url, false|string $outFile = false, array $curlOpts = []): bool|string
    {
        global $api;

        // Remove hash from URL
        $url = preg_replace("@#.*$@", "", $url);

        $api->log->info($this->logPrefix, 'Downloading ' . $url, ["url" => $url, 'opts' => $curlOpts]);
        $ch = curl_init($url)
            or throw new Exception('Failed to initialize CURL with URL ' . $url);

        $file = false;
        if (is_string($outFile)) {
            curl_setopt($ch, CURLOPT_FILE, $file = $this->curlPrepareOutputFile($outFile));
        } else { // important: you cannot set both CURLOPT_FILE and CURLOPT_RETURNTRANSFER to any value, 
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }

        $curlOpts = array_replace(
            [
                CURLOPT_USERAGENT => $this->uaStrings[array_rand($this->uaStrings)]
            ],
            self::DEFAULT_CURL_OPTS,
            [
                CURLOPT_REFERER => $curlOpts[CURLOPT_REFERER] ?? "https://www.google.com/?source=osdd&sl=auto&tl=auto&text=" . parse_url($url, PHP_URL_HOST) . "&op=translate",
                CURLOPT_COOKIEFILE => $this->cookieJarFileName, // Read init cookies from here. By passing the empty string ("") to this option, you enable the cookie engine without reading any initial cookies.
                CURLOPT_COOKIEJAR => $this->cookieJarFileName, // Write cookies here.
            ],
            $curlOpts
        );

        /** @var \CurlHandle $ch */
        try {
            // curl_setopt_array($ch, $curlOpts);
            foreach ($curlOpts as $opt => $val) {
                curl_setopt($ch, $opt, $val);
            }
        } catch (\Throwable $e) {
            $api->log->error(
                $this->logPrefix,
                "Failed to set CURL options, invalid option {$opt} or value " . json_encode($val, JSON_UNESCAPED_SLASHES),
                ["url" => $url, "opts" => $curlOpts, "error" => $e->getMessage()]
            );
            curl_close($ch);
            return false;
        }

        $result = curl_exec($ch);
        curl_close($ch);
        if ($file) fclose($file);

        $this->curlCheckResult($url, $result, $outFile, $curlOpts, $ch);

        return $result;
    }

    private function curlCheckResult(string $url, $result, mixed $outFile, array $curlOpts, \CurlHandle $ch)
    {
        global $api;

        $errMsg = curl_error($ch);
        $errNo = curl_errno($ch);

        if (!$result || $errNo) {
            // Grep all possible info about the failure
            $api->log->error($this->logPrefix, "CURL: Failed to download $url ($errNo $errMsg)", [
                "url" => $url,
                'error' => $errMsg,
                'errno' => $errNo,
                'info' => curl_getinfo($ch),
                'opts' => array_filter($curlOpts, fn ($k) => $k !== CURLOPT_FILE) // file is resource - not serializable
            ]);
            // See https://curl.se/libcurl/c/libcurl-errors.html
            switch ($errNo) {
                case CURLE_OPERATION_TIMEOUTED:
                    throw new TimeoutException("Timeout downloading $url", $errNo);
                case CURLE_SSL_CACERT_BADFILE:
                case CURLE_SSL_CERTPROBLEM:
                case CURLE_SSL_CIPHER:
                case CURLE_SSL_CONNECT_ERROR:
                case CURLE_SSL_ENGINE_NOTFOUND:
                case CURLE_SSL_ENGINE_SETFAILED:
                case CURLE_SSL_PINNEDPUBKEYNOTMATCH:
                    // 35 OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to www.tmdn.org:443
                    throw new SslException("SSL error downloading $url: $errNo $errMsg", $errNo);
                case CURLE_GOT_NOTHING:
                    throw new GotNothingException("Empty reply downloading $url: $errNo $errMsg", $errNo);
                default:
                    throw new Exception("Failed to download $url: $errNo $errMsg", $errNo);
            }
            throw new Exception("Failed to download $url: $errNo $errMsg", $errNo);
        } else {
            $size = is_string($outFile) ? filesize($outFile) : strlen($result);
            $sizeHuman = $api->convert->memoryUnits($size, "MiB", 3) . ' MiB';
            $api->log->info($this->logPrefix, "CURL: Downloaded $url ($sizeHuman)", [
                "url" => $url,
                "error" => $errMsg,
                "errno" => $errNo,
                'info' => curl_getinfo($ch),
                'size' => $size,
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
}
