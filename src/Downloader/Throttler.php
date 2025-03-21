<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader;

/**
 * This is the component used by the DownloaderService to throttle the download speed.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-06-25
 */
class Throttler {
    private array $domains = [];

    public function __construct() {
        global $api;

        foreach ($api->config['downloader']['throttle'] as $host => $throttle) {
            if (!is_array($throttle)) {
                trigger_error('Throttler: Invalid throttle configuration for host ' . json_encode($host) . ': ' . json_encode($throttle), E_USER_WARNING);
                $throttle = ['time' => 0, 'max' => 0];
            }
            $this->domains[$host] = new ThrottlerDomain($host, $throttle['time'], $throttle['max']);
        }
        $this->domains['default'] = new ThrottlerDomain('default', 0, 0);
    }

    /**
     * Check if the host is over the download speed limit.
     * 
     * @param string $url
     * @return bool
     */
    public function isOverLimit(string $url): bool {
        $domain = $this->getDomain($url);
        return $domain->isOverLimit();
    }

    public function getRemainingTime(string $url): int {
        $domain = $this->getDomain($url);
        return $domain->getRemainingTime();
    }

    /**
     * Record the request time for the last request.
     *
     * @param ?integer $time The time of the request. If null, the current time is used.
     * @return void
     */
    public function recordRequest(string $url, ?int $time = null): void {
        $domain = $this->getDomain($url);
        $domain->recordRequest($time);
    }

    public function getInfoText(string $url): string {
        $domain = $this->getDomain($url);
        return $domain->getInfoText();
    }

    private function getDomain(string $url): ThrottlerDomain {
        $host = parse_url($url, PHP_URL_HOST) ?? $url;

        foreach ($this->domains as $domain) {
            if ($domain->matchHost($host)) {
                return $domain;
            }
        }
        return $this->domains['default'];
    }   
}