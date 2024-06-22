<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader;

use Zolinga\Commons\Downloader\DownloaderService;
use Zolinga\Commons\Downloader\Exception\GotNothingException;
use Zolinga\Commons\Downloader\Exception\SslException;
use Zolinga\Commons\Downloader\Exception\TimeoutException;

/**
 * This is the extension to the DownloaderService class that adds the API
 * to refresh the identity and implements some basic anonymization features.
 * 
 * It is meant to be extended by Tor or other Proxy/Anonymizing services.
 * 
 * It is not Abstract as in rare cases it can be used as a standalone service
 * with Anonymizer API yet without the proxy. (e.g. for testing purposes)
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-06-13
 */
class Anonymizer extends DownloaderService
{
    private const IP_SERVICES = [
        'https://api.ipify.org',
        'https://icanhazip.com',
        'https://ipinfo.io/ip',
        'https://ip.seeip.org',
        'https://ipapi.co/ip',
    ];

    protected ?string $lastIP = null;
    private array $onAnonymizeHooks = [];
    private bool $inAnonymizeCallback = false;

    public function __construct(string $downloaderName = 'tor')
    {
        global $api;
        parent::__construct($downloaderName);
        $this->setTimeout();
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
     * @param int $downloaderOpts Additional downloader options. E.g. DownloaderService::OPT_KEEP_ALIVE
     * @return boolean|string Content of the resource if $outFile is false, otherwise true on success.
     */
    public function download(string $url, false|string $outFile = false, array $curlOpts = [], int $downloaderOpts = 0): bool|string
    {
        global $api;
        $ret = false;
        $curlOptsLocal = [];
        $failFast = $downloaderOpts & self::FAIL_FAST;

        $attempts = 3;
        do {
            try {
                $ret = parent::download($url, $outFile, array_replace($curlOptsLocal, $curlOpts));
            } catch (TimeoutException | SslException | GotNothingException $e) {
                if ($failFast) {
                    throw $e;
                } elseif ($attempts-- <= 0 || $this->qos->getStats()['successRatio'] <= 0.5) {
                    $this->anonymize();
                }
            } catch (\Throwable $e) {
                $api->log->error($this->downloaderName, "Failed to download $url: " . $e->getCode() . ' ' . $e->getMessage());
                throw $e;
            }
        } while (!$ret);

        return $ret;
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
    public function onAnonymize(callable $callback): void
    {
        $this->onAnonymizeHooks[] = $callback;
    }

    /**
     * Change the exit node (e.g. refresh Tor circuit or change Proxy IP...)
     * 
     * This method should be implemented in the extending class.
     * 
     * It also calls the callbacks added by `$this->onAnonymize()`.
     *
     * @throws \InvalidArgumentException If the Tor control host, port, or password is not set in the configuration.
     * @throws \Exception If there is an error connecting to the Tor control port, authenticating, or sending the NEWNYM signal.
     * @return void
     */
    public function anonymize(): void
    {
        $this->closeConnection(); // close persistent curl connections
        $this->processAnonymizeHooks();
    }

    protected function excludeExitNode(string $node): void {
        // implement exclusion of exit nodes
    }


    /**
     * Returns the current IP address of the Tor service.
     *
     * @return string|null The current IP address of the Tor service or null if the IP address could not be determined.
     */
    public function getMyIP(): ?string
    {
        $try = self::IP_SERVICES;
        do {
            try {
                $ip = $this->download(array_shift($try), downloaderOpts: self::FAIL_FAST);
                if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                    $ip = null;
                }
            } catch (\Throwable $e) {
                $ip = null;
            }
        } while (!$ip && $try);

        return $ip ?: null;
    }

    /**
     * Process the callbacks added by `$this->onAnonymize()`.
     *
     * @return void
     */
    private function processAnonymizeHooks(): void
    {
        if (!$this->inAnonymizeCallback) {
            try {
                $this->inAnonymizeCallback = true;
                foreach ($this->onAnonymizeHooks as $callback) {
                    $callback($this);
                }
            } finally {
                $this->inAnonymizeCallback = false;
            }
        }
    }
}
