<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader;

use Zolinga\Commons\Downloader\DownloaderService;
use Zolinga\Commons\Downloader\Exception\GotNothingException;
use Zolinga\Commons\Downloader\Exception\SslException;
use Zolinga\Commons\Downloader\Exception\TimeoutException;

/**
 * Tor Service API
 * 
 * It expects the Tor to be running with SOCKS5 proxy on on the address specified 
 * in config's tor.proxy.host and tor.proxy.port (default 127.0.0.1:9050)
 * 
 * In order to use control port you need to specify the password in config's tor.control.password
 * alongside with the address and port (default 127.0.0.1:9051)
 * 
 * @property-read int $requestCounter Number of requests made since the last identity refresh.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-06-13
 */
class TorService extends DownloaderService
{
    public const FLAG_CHANGE_IP = 0;
    public const FLAG_FLUSH_COOKIES = 1;
    public const FLAG_RANDOMIZE_USER_AGENT = 2;
    public const FLAG_CHANGE_ALL = 3;

    private readonly string $controlHost;
    private readonly int $controlPort;
    private readonly string $controlPassword; 
    private array $timeoutSettingsStack = [];
    private $lastIP = null;
    private $blacklist = [];
    private array $onIndentityChangeHooks = [];
    private bool $inCallback = false;

    public function __construct(string $downloaderName = 'tor')
    {
        global $api;
        parent::__construct($downloaderName);

        $socksHost = $api->config['tor']['proxy']['host']
            or throw new \Exception("Tor proxy host not set in config.");
        $socksPort = $api->config['tor']['proxy']['port']
            or throw new \Exception("Tor proxy port not set in config.");

        $this->setOpts([
            CURLOPT_PROXY => "$socksHost:$socksPort",
            CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5,
        ]);

        $this->controlHost = $api->config['tor']['control']['host'];
        $this->controlPort = $api->config['tor']['control']['port'];
        $this->controlPassword = $api->config['tor']['control']['password'];

        $api->log->info($this->downloaderName, "Tor proxy set to $socksHost:$socksPort (control port: {$this->controlHost}:{$this->controlPort})");
        $this->setTimeout();
    }

    private function isControlConfigured(): bool
    {
        return $this->controlHost && $this->controlPort && $this->controlPassword;
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
        $this->timeoutSettingsStack[] = [$timeout, $connectionTimeout];
        $this->setTimeoutSettings($timeout, $connectionTimeout);
    }

    /**
     * Set the previous timeout settings that were set before the last call to `$this->setTimeout()`.
     *
     * @return array{integer, integer} The previous timeout settings.
     */
    public function unsetTimeout(): array
    {
        array_pop($this->timeoutSettingsStack);
        [$timeout, $connectionTimeout] = end($this->timeoutSettingsStack);
        $this->setTimeoutSettings($timeout, $connectionTimeout);
        return [$timeout, $connectionTimeout];
    }

    private function setTimeoutSettings(int $timeout, int $connectionTimeout): void
    {
        global $api;

        $api->log->info($this->downloaderName, "Setting default timeout to $timeout seconds and connection timeout to $connectionTimeout seconds.");
        $this->setOpts([
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_TIMEOUT_MS => $timeout * 1000,
            CURLOPT_CONNECTTIMEOUT => $timeout / 2,
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME => $connectionTimeout,
            CURLOPT_CONNECTTIMEOUT => $connectionTimeout,
            CURLOPT_CONNECTTIMEOUT_MS => $connectionTimeout * 1000,
        ]);
    }

    public function __get(string $name): mixed
    {
        switch ($name) {
            case 'requestCounter':
                return $this->qos->getStats()['total'];
            default:
                return parent::__get($name);
        }
    }

    /**
     * Download a remote resource using CURL.
     * 
     * By default it stores cookies persistently into $this->cookieJarFileName 
     * stored in private://ipdefender-daq/cookies/ directory. It reuses cookies
     * for subsequent requests. It also follows redirects by default.
     * 
     * This is a wrapper for `Zolinga\Commons\Downloader\DownloaderService::download()` method.
     *
     * @param string $url
     * @param false|boolean $outFile false to return the content, string to save to file.
     * @param array<mixed> $curlOpts Additional CURL options. E.g. [CURLOPT_PROXY => 'http://proxy:port']
     * @return boolean|string Content of the resource if $outFile is false, otherwise true on success.
     */
    public function download(string $url, false|string $outFile = false, array $curlOpts = []): bool|string
    {
        global $api;
        $ret = false;
        $curlOptsLocal = [];

        // Trying to optimize DNS queries using local query to lower the TOR latency...
        // $host = parse_url($url, PHP_URL_HOST);
        // $port = parse_url($url, PHP_URL_PORT) ?: 443;
        // $ip = gethostbyname($host);
        // if ($ip !== $host) {
        //     $curlOptsLocal[CURLOPT_RESOLVE] = ["$host:$port:$ip"];
        //     $curlOptsLocal[CURLOPT_CONNECT_TO] = ["$host:$port:$ip"];
        // }

        $attempts = 3;
        do {
            try {
                $ret = parent::download($url, $outFile, array_replace($curlOptsLocal, $curlOpts));
            } catch (TimeoutException | SslException | GotNothingException $e) {
                if ($attempts-- <= 0 || $this->qos->getStats()['successRatio'] <= 0.5) {
                    $this->refreshIdentity();
                }
            } catch (\Throwable $e) {
                $api->log->error($this->downloaderName, "Failed to download $url: " . $e->getCode() . ' ' . $e->getMessage());
                throw $e;
            }
        } while (!$ret);

        return $ret;
    }

    /**
     * Test the connection to a well-known URL. By default it will try hard to download the page
     * through $this->download() that will retry on failure and refresh the identity if necessary.
     * 
     * So when this method returns true, the connection is stable and the Tor service is working.
     * 
     * The test uses stricter timeout settings than the default ones (10s, 5s) to make sure the connection is stable.
     *
     * @return bool Returns true if the connection test is successful, false otherwise.
     */
    public function testConnection()
    {
        global $api;

        $api->log->info($this->downloaderName, "Connection Test: Testing TOR connection (example.com)...");
        $this->setTimeout(10, 5);
        try {
            $html = $this->download('https://example.com/', false);

            if (!$html || strpos($html, 'Example Domain') === false) {
                throw new \Exception("Failed to download test page.");
            }
            $api->log->info($this->downloaderName, "Connection Test: Connection test successful.");
            return true;
        } catch (\Throwable $e) {
            $api->log->error($this->downloaderName, "Connection Test: Connection test failed: " . $e->getMessage());
            return false;
        } finally {
            $this->unsetTimeout();
        }
    }

    /**
     * Add a callback that will be called when the identity of the Tor service is refreshed.
     * 
     * The callback will receive the TorService instance as the first argument. Since
     * the callback is called after the identity is refreshed, you can use the instance
     * to set cookies, user agent, etc. to the new identity.
     * 
     * Rembmber that refreshing identity by default flushes cookies and randomizes user agent.
     *
     * @param callable $callback The callback to add.
     * @return void
     */
    public function addRefreshIdentityCallback(callable $callback): void
    {
        $this->onIndentityChangeHooks[] = $callback;
    }

    /**
     * Refreshes the identity of the Tor service by authenticating with the control port and sending a NEWNYM signal.
     * 
     * Control Port must be configured and settings must be set in the config's tor.control section.
     *
     * @throws \InvalidArgumentException If the Tor control host, port, or password is not set in the configuration.
     * @throws \Exception If there is an error connecting to the Tor control port, authenticating, or sending the NEWNYM signal.
     * @param int $flags Flags to control the behavior of the method. Default is to change all (IP, User Agent, flush cookies).
     *            Use the constants FLAG_FLUSH_COOKIES, FLAG_RANDOMIZE_USER_AGENT, FLAG_CHANGE_IP, or FLAG_CHANGE_ALL to control the behavior.
     *            You can combine the flags using the bitwise OR operator (|). FLAG_CHANGE_IP is always implied.
     * @return void
     */
    public function refreshIdentity(int $flags = self::FLAG_CHANGE_ALL): void
    {
        global $api;

        $successRatioPct = round($this->qos->getStats()['successRatio'] * 100);
        if ($successRatioPct <= 50) { // if we downloaded a lot of pages we may want to keep the IP usable
            $this->blacklist[] = $this->lastIP;
            $api->log->warning($this->downloaderName, "IP address {$this->lastIP} blacklisted due to low success ratio ($successRatioPct%).");
        }
        
        $this->qos->setExitNode('*TOR Identity Pings*'); // pings to find out IP address

        do {
            $this->sendNewNymSignal();
            $info = [];

            if ($flags & self::FLAG_RANDOMIZE_USER_AGENT) {
                $info[] = 'randomize user agent';
                $this->randomizeUserAgent();
            } else {
                $info[] = 'keep user agent';
            }

            if ($flags & self::FLAG_FLUSH_COOKIES) {
                $info[] = 'flush cookies';
                $this->flushCookies();
            } else {
                $info[] = 'keep cookies';
            }

            $ip = $this->getMyIP();

            if ($ip === $this->lastIP) {
                $api->log->warning($this->downloaderName, "Failed to change IP address ($ip). Retrying...");
                $retry = true;
            } elseif (in_array($ip, $this->blacklist)) {
                $api->log->warning($this->downloaderName, "IP address $ip is blacklisted. Retrying...");
                $retry = true;
            } else {
                $retry = false;
            }
        } while ($retry);

        $this->lastIP = $ip;
        $info[] = "new IP {$ip}";
        $this->qos->setExitNode($ip);
        $api->log->info($this->downloaderName, "Refreshed TOR circuit (" . implode(', ', $info) . ")...");

        if (!$this->inCallback) {
            try {
                $this->inCallback = true;
                foreach ($this->onIndentityChangeHooks as $callback) {
                    $callback($this);
                }
            } finally {
                $this->inCallback = false;
            }
        }
    }

    private function sendNewNymSignal()
    {
        global $api;
        static $lastSignal = 0;

        $last = $lastSignal ? time() - $lastSignal : 10;
        sleep(max(0, 10 - $last));
        $lastSignal = time();

        if (!$this->isControlConfigured()) {
            $this->controlHost
                or throw new \InvalidArgumentException("Tor control host not set in config key tor.control.host");
            $this->controlPort
                or throw new \InvalidArgumentException("Tor control port not set in config key tor.control.port");
            $this->controlPassword
                or throw new \InvalidArgumentException("Tor control password not set in config key tor.control.password");
        }

        $fp = fsockopen($this->controlHost, $this->controlPort, $errno, $errstr, 30)
            or throw new \Exception("Failed to connect to Tor control port {$this->controlHost}:{$this->controlPort}: $errstr ($errno)");

        fwrite($fp, "AUTHENTICATE \"$this->controlPassword\"\r\n");
        $response = trim(fread($fp, 1024));
        if (strpos($response, "250") !== 0) {
            throw new \Exception("Failed to authenticate to Tor control port: $response");
        }
        // $api->log->info($this->downloaderName, "Authenticated to Tor control port: $response");

        fwrite($fp, "SIGNAL NEWNYM\r\n");
        $response = trim(fread($fp, 1024));
        if (strpos($response, "250") !== 0) {
            throw new \Exception("Failed to send NEWNYM signal to Tor control port: $response");
        }
        // $api->log->info($this->downloaderName, "Sent NEWNYM signal to Tor control port: $response");

        fclose($fp);
    }

    /**
     * Returns the current IP address of the Tor service.
     *
     * @return string|null The current IP address of the Tor service or null if the IP address could not be determined.
     */
    public function getMyIP(): string
    {
        $try = [
            'https://api.ipify.org',
            'https://icanhazip.com',
            'https://ipinfo.io/ip',
            'https://ip.seeip.org',
            'https://ipapi.co/ip',
        ];

        $ip = null;
        do {
            try {
                $ip = $this->download(array_shift($try), false);
            } catch (\Throwable $e) {
                $ip = null;
            }
        } while (!$ip && $try);

        return $ip ?: null;
    }
}
