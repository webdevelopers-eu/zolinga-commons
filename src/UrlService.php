<?php

declare(strict_types=1);

namespace Zolinga\Commons;

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

    /**
     * Get current URL.
     *
     * @return string
     */
    public function getCurrentUrl(): string
    {
        return (IS_HTTPS ? "https://" : "http://") .
            $_SERVER["HTTP_HOST"] .
            // Port
            (
                ($_SERVER["SERVER_PORT"] == 80 && !IS_HTTPS) || ($_SERVER["SERVER_PORT"] == 443 && IS_HTTPS)
                ? "" : ":" . $_SERVER["SERVER_PORT"]
            ) . $_SERVER["REQUEST_URI"];
    }

    /**
     * Make URL absolute.
     *
     * Examples:
     * echo $api->url->resolveUrl("test", "http://example.com"); // http://example.com/test
     * echo $api->url->resolveUrl("test", "http://example.com/dir/"); // http://example.com/dir/test
     * echo $api->url->resolveUrl("test", "http://example.com/dir"); // http://example.com/test
     *
     * @param string $url
     * @param ?string $base if not given then current URL is used
     */
    public function resolveUrl(string $url, ?string $base = null): string
    {
        if ($base === null) {
            $base = $this->getCurrentUrl();
        }
        if (preg_match("@^https?://@", $url)) { // is absolute already
            return $url;
        }

        $baseParts = parse_url($base);

        $prefix = $baseParts["scheme"] . ":";
        if (preg_match("@^//@", $url)) { // is protocol relative
            return $prefix . $url;
        }

        $prefix .= "//" . $baseParts["host"] . (isset($baseParts["port"]) ? ":" . $baseParts["port"] : "");
        if (preg_match("@^/@", $url)) { // is root relative
            return $prefix . $url;
        }

        if (preg_match("@^[^?#]@", $url)) { // is relative
            $prefix .= isset($baseParts['path']) ? preg_replace("@/[^/]*$@", "/", $baseParts["path"]) : "/";
            return $prefix . $url;
        } else {
            $prefix .= $baseParts["path"];
        }

        if (substr($url, 0, 1) == "?") {
            return $prefix . $url;
        }

        if (substr($url, 0, 1) == "#") {
            $prefix .= isset($baseParts["query"]) ? "?" . $baseParts["query"] : "";
            return $prefix . $url;
        }

        return $base;
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
}
