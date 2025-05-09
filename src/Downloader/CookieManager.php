<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader;

use Exception;

class CookieManager
{
    public private(set) string $cookieJarFileName;

    public function __construct(string $cookieJarFileName)
    {
        $this->initCookieJar($cookieJarFileName);
        // Constructor is intentionally empty as per requirements.
        // Dependencies like $api->log and $api->fs will be accessed via global $api.
    }

    private function initCookieJar(string $cookieJarFileName): void
    {
        global $api;
        
        if (!is_dir(dirname($cookieJarFileName))) {
            // Attempt to create the directory
            if (!mkdir(dirname($cookieJarFileName), 0777, true) && !is_dir(dirname($cookieJarFileName))) {
                // Check again if directory exists, to handle potential race conditions or if it was created by another process
                throw new Exception(sprintf('Directory "%s" was not created', dirname($cookieJarFileName)));
            }
        }

        if (!touch($cookieJarFileName)) {
            throw new Exception("Failed to create cookie jar file $cookieJarFileName");
        }

        $this->cookieJarFileName = $cookieJarFileName;
        $api->log->info("CookieManager", "Cookie jar initialized at $cookieJarFileName");
    }

    public function getCookies(?string $domain = null, bool $full = false): array
    {
        $ret = [];

        $cookiesContent = file_get_contents($this->cookieJarFileName);
        // Check if file_get_contents failed
        if ($cookiesContent === false) {
            return [];
        }

        foreach (explode("\n", $cookiesContent) as $cookieLine) {
            // Skip empty lines or comments
            if (empty($cookieLine) || $cookieLine[0] === '#') {
                continue;
            }
            $cookieParts = explode("\t", $cookieLine);
            if (count($cookieParts) < 7) {
                continue;
            }
            $cookieData = [
                "domain" => $cookieParts[0],
                "flag" => $cookieParts[1],
                "path" => $cookieParts[2],
                "secure" => $cookieParts[3],
                "expiration" => $cookieParts[4],
                "name" => $cookieParts[5],
                "value" => $cookieParts[6],
            ];

            // Filter by domain if specified
            if ($domain === null || str_ends_with($cookieData['domain'], $domain) || $cookieData['domain'] === $domain || (strpos($cookieData['domain'], '.') === 0 && str_ends_with($domain, $cookieData['domain']))) {
                $ret[] = $cookieData;
            }
        }

        return $full ? $ret : array_column($ret, 'value', 'name');
    }

    public function flushCookies(): void
    {
        global $api;
        
        // Ensure the file exists before trying to clear it, or simply create/truncate it
        if (file_put_contents($this->cookieJarFileName, '') === false) {
            throw new Exception("Failed to flush cookie jar file: {$this->cookieJarFileName}");
        }
    }
}
