<?php

declare(strict_types=1);

namespace Zolinga\Commons;

use Zolinga\Intl\LocaleService;
use Zolinga\System\Events\ServiceInterface;
use const Zolinga\System\IS_HTTPS;

/**
 * Various URL related functions.
 *
 * @author Daniel Sevcik <daniel@zolinga.net>
 * @date 2024-04-26
 */
class UrlService implements ServiceInterface
{

    private array $validatedURLCache = [];

    /**
     * Get current URL.
     *
     * @return string
     */
    public function getCurrentUrl(): string
    {
        global $api;

        $host = isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : null;

        if (!$host) {
            return $api->config['baseURL'] ?? '';
        }
        
        // Port
        $port = parse_url($host, PHP_URL_PORT);
        $isStandard = !$port || ($port === 80 && !IS_HTTPS) || ($port === 443 && IS_HTTPS);
        if (!$port && !$isStandard) {
            $host .= ":" . $_SERVER["SERVER_PORT"];
        } elseif ($port && $isStandard) {
            $host = str_replace(":" . $port, "", $host);
        }

        return (IS_HTTPS ? "https://" : "http://") . $host . $_SERVER["REQUEST_URI"];
    }

    /**
     * Make URL absolute.
     *
     * Examples:
     * echo $api->url->resolveUrl("test", "http://example.com"); // http://example.com/test
     * echo $api->url->resolveUrl("test", "http://example.com/dir/"); // http://example.com/dir/test
     * echo $api->url->resolveUrl("test", "http://example.com/dir"); // http://example.com/test
     * 
     * echo $api->url->resolveUrl("/test", lang: "en_US"); // http://example.com/en/test
     * 
     *
     * @param string $url
     * @param ?string $base if not given then current URL is used
     * @param ?string $lang if given, it is added as "lang" query parameter to the resolved URL. If lang is already present in the URL, it is replaced.
     */
    public function resolveUrl(string $url, ?string $base = null, ?string $lang = null): string
    {
        global $api;

        if ($base === null) {
            $base = $this->getCurrentUrl();
        }
        
        $baseParts = parse_url($base);
        $parts = parse_url($url);
        $merged = array_merge($baseParts, $parts);

        if (!preg_match("@^/@", $merged['path'] ?? '')) { // is relative path
            $merged['path'] = rtrim($baseParts["path"] ?? '', '/') . "/" . ($merged['path'] ?? '');
        }
        if ($lang) {
            // Remove old lang parameter if present
            $merged['path'] = preg_replace("/^\/[a-z]{2}(\/|$)/", "\\1", $merged['path']);
            $merged['path'] = "/" . \Locale::getPrimaryLanguage($lang) . $merged['path'];
        }

        // Glue back together
        $mergedURL = ($merged['scheme'] ?? 'https') . "://";
        if (isset($merged['user']) && isset($merged['pass'])) {
            $mergedURL .= $merged['user'] . ":" . $merged['pass'] . "@";
        }
        $mergedURL .= $merged['host'] ?? 'localhost';
        if (isset($merged['port'])) {
            $mergedURL .= ":" . $merged['port'];
        }
        $mergedURL .= $merged['path'] ?? '/';
        if (isset($merged['query'])) {
            $mergedURL .= "?" . $merged['query'];
        }
        if (isset($merged['fragment'])) {
            $mergedURL .= "#" . $merged['fragment'];
        }

        return $mergedURL;
    }

    /**
     * Make URL name from text.
     *
     * Examples:
     * echo $api->url->makeUrlPathPart("Hello, World!"); // hello-world
     * echo $api->url->makeUrlPathPart("Dobrý večer!"); // dobry-vecer
     * 
     * @param string $text
     * @return string
     */
    public function makeUrlPathPart(string $text): string
    {
        $text = iconv("UTF-8", "ASCII//TRANSLIT", $text);
        $text = strtolower($text);
        $text = preg_replace("@[^a-z0-9]+@", "-", $text);
        $text = preg_replace("@-+@", "-", $text);
        $text = trim($text, "-");
        return $text;
    }

    private function addValidatedURLToCache(string $url, bool $isValid): bool
    {
        $this->validatedURLCache[$url] = $isValid;
        if (count($this->validatedURLCache) > 1000) {
            array_shift($this->validatedURLCache); // simple cache eviction
        }

        return $isValid;
    }

    /**
     * Download URL and check if it is valid (returns 200 response).
     *
     * @param string $url can be relative or absolute URL
     * @return boolean true if valid, false otherwise
     */
    public function isValidURL(string $url): bool
    {
        global $api;

        $fqURL = $api->url->resolveUrl($url, $api->config['baseURL']);
        $fqURL = preg_replace("/#.*$/", "", $fqURL); // remove fragment
        $fqURL = filter_var($fqURL, FILTER_SANITIZE_URL, FILTER_VALIDATE_URL);

        if (!$fqURL) {
            $api->log->warning("url", "Invalid URL format: $url");
            return $this->addValidatedURLToCache($fqURL, false);
        }

        if (isset($this->validatedURLCache[$fqURL])) {
            return $this->validatedURLCache[$fqURL];
        }

        // Check if it is 200 response
        try {
            $ua = 'Mozilla/5.0 (compatible; ZolingaBot/1.0; +https://zolinga.net) link-checker';
            $api->downloader->download($fqURL, curlOpts: [CURLOPT_USERAGENT => $ua, CURLOPT_REFERER => ""]);
            $api->log->info("url", "Validated link: $fqURL");
        } catch (\Exception $e) {
            $api->log->warning("url", "Invalid link: $fqURL . Reason: " . $e->getMessage());
            return $this->addValidatedURLToCache($fqURL, false);
        }

        return $this->addValidatedURLToCache($fqURL, true);
    }
}
