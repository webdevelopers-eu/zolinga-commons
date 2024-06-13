<?php

declare(strict_types=1);

namespace Zolinga\Commons\Tor;

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
    private array $curlOpts;
    private readonly string $controlHost;
    private readonly int $controlPort;
    private readonly string $controlPassword;
    private int $requestCounter = 0;

    public function __construct(string $cookieJarName = 'tor')
    {
        global $api;
        parent::__construct($cookieJarName);

        $socksHost = $api->config['tor']['proxy']['host']
            or throw new \Exception("Tor proxy host not set in config.");
        $socksPort = $api->config['tor']['proxy']['port']
            or throw new \Exception("Tor proxy port not set in config.");

        $this->curlOpts = [
            CURLOPT_PROXY => "$socksHost:$socksPort",
            CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5,
        ];
        $this->setDefaultTimeout();

        $this->controlHost = $api->config['tor']['control']['host'];
        $this->controlPort = $api->config['tor']['control']['port'];
        $this->controlPassword = $api->config['tor']['control']['password'];
    }

    private function isControlConfigured(): bool
    {
        return $this->controlHost && $this->controlPort && $this->controlPassword;
    }

    /**
     * TOR service can be very slow. Set the default timeout.
     *
     * @param integer $timeout Timeout in seconds. Default is 60.
     * @return void
     */
    public function setDefaultTimeout(int $timeout = 60): void
    {
        $this->curlOpts[CURLOPT_TIMEOUT] = $timeout;
        $this->curlOpts[CURLOPT_TIMEOUT_MS] = $timeout * 1000;
        $this->curlOpts[CURLOPT_CONNECTTIMEOUT] = $timeout / 2;
    }

    public function __get(string $name): mixed
    {
        switch ($name) {
            case 'requestCounter':
                return $this->requestCounter;
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
        $this->requestCounter++;
        $ret = false;
        $curlOptsLocal = [];

        // Trying to optimize DNS queries using local query to lower the TOR latency...
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?: 443;
        $ip = gethostbyname($host);
        if ($ip !== $host) {
            $curlOptsLocal[CURLOPT_RESOLVE] = ["$host:$port:$ip"];
            $curlOptsLocal[CURLOPT_CONNECT_TO] = ["$host:$port:$ip"];
        }

        do {
            try {
                $ret = parent::download($url, $outFile, array_replace($this->curlOpts, $curlOptsLocal, $curlOpts));
            } catch (TimeoutException | SslException | GotNothingException $e) { // Slow/unusable Tor service
                // Try if the connection works
                $this->refreshIdentity();
            } catch (\Throwable $e) {
                $api->log->error($this->logPrefix, "Failed to download $url: " . $e->getCode() . ' ' . $e->getMessage());
                throw $e;
            }
        } while (!$ret);

        return $ret;
    }

    // private function testConnection()
    // {
    //     global $api;

    //     try {
    //         $html = parent::download('https://example.com/', false, $this->curlOpts);

    //         if (!$html || strpos($html, 'Example Domain') === false) {
    //             throw new \Exception("Failed to download test page.");
    //         }
    //         $api->log->info($this->logPrefix, "Connection test successful.");
    //         return true;
    //     } catch (\Throwable $e) {
    //         $api->log->error($this->logPrefix, "Connection test failed: " . $e->getMessage());
    //         return false;
    //     }
    // }

    /**
     * Refreshes the identity of the Tor service by authenticating with the control port and sending a NEWNYM signal.
     * 
     * Control Port must be configured and settings must be set in the config's tor.control section.
     *
     * @throws \InvalidArgumentException If the Tor control host, port, or password is not set in the configuration.
     * @throws \Exception If there is an error connecting to the Tor control port, authenticating, or sending the NEWNYM signal.
     */
    public function refreshIdentity(bool $flushCookies = false): void
    {
        global $api;

        $api->log->info($this->logPrefix, "Refreshing TOR circuit (" . ($flushCookies ? 'flushing cookies' : 'keeping cookies') . ")...");

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
        // $api->log->info($this->logPrefix, "Authenticated to Tor control port: $response");

        fwrite($fp, "SIGNAL NEWNYM\r\n");
        $response = trim(fread($fp, 1024));
        if (strpos($response, "250") !== 0) {
            throw new \Exception("Failed to send NEWNYM signal to Tor control port: $response");
        }
        // $api->log->info($this->logPrefix, "Sent NEWNYM signal to Tor control port: $response");

        fclose($fp);
        $this->requestCounter = 0;

        if ($flushCookies) {
            $this->flushCookies();
        }
    }
}
