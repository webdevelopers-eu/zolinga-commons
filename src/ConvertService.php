<?php

declare(strict_types=1);

namespace Zolinga\Commons;

use Dom\HTMLDocument;
use DOMDocument;
use Zolinga\System\Events\ServiceInterface;
use NumberFormatter;


/**
 * Various formatting functions.
 * 
 * Available as $api->convert
 */
class ConvertService implements ServiceInterface
{
    private const MEMORY_UNITS = [
        "B" => 1,
        "K" => 1024,
        "M" => 1024 * 1024,
        "G" => 1024 * 1024 * 1024,
        "T" => 1024 * 1024 * 1024 * 1024,
        "KiB" => 1024,
        "MiB" => 1024 * 1024,
        "GiB" => 1024 * 1024 * 1024,
        "TiB" => 1024 * 1024 * 1024 * 1024,
        "KB" => 1000,
        "MB" => 1000 * 1000,
        "GB" => 1000 * 1000 * 1000,
        "TB" => 1000 * 1000 * 1000 * 1000,
    ];

    /**
     * Convert between units of memory.
     * 
     * Example:
     * 
     * $api->convert->memoryUnits("1.5 GiB", "MiB", 2); // 1536.00
     * $api->convert->memoryUnits(1024, "KiB"); // 1
     * $api->convert->memoryUnits("2KB"); // 2000
     *
     * @param integer|string $size if int: bytes, if string then it may contain a number and a unit
     * @param string $out one of the keys of self::MEMORY_UNITS
     * @param integer $precision number of decimal places
     * @return float|integer converted value, if $precision=0 integer is returned otherwise float
     */
    public function memoryUnits(int|string $size, string $out = "B", int $precision = 0): float|int
    {
        global $api;

        if (is_numeric($size)) { // bytes
            $size = (int) $size;
        } elseif (preg_match('/^(?<size>\d([\d.,]*\d)?)\s*(?<unit>'.implode("|", array_keys(self::MEMORY_UNITS)).')$/', trim($size), $matches)) {
            // May contain a comma as a decimal separator...
            $val = is_numeric($matches['size']) ? (float) $matches['size'] : $api->local->stringToFloat($matches['size']);
            $size = $val * self::MEMORY_UNITS[$matches['unit']];
        }

        if (!is_int($size) && !is_float($size)) {
            throw new \Exception("Invalid size format: $size");
        }

        $ret = round($size / self::MEMORY_UNITS[$out], $precision);
        return $precision ? (float) $ret : (int) $ret;
    }

    /**
     * Convert seconds to a human-readable time delta.
     * 
     * Example:
     * 
     * $api->convert->timeDelta(3665, 2); // "1 hour 1 minute"
     *
     * @param float $seconds
     * @param int $maxParts Maximum number of parts to include (e.g., 2 would return "1 hour 1 minute" instead of "1 hour 1 minute 5 seconds")
     * @return string
     */
    public function timeDelta(float $seconds, int $maxParts): string
    {
        $dtF = new \DateTimeImmutable('@0');
        $dtT = new \DateTimeImmutable("@$seconds");
        $diff = $dtF->diff($dtT);

        $parts = [];
        if ($diff->y) {
            $parts[] = $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
        }
        if ($diff->m) {
            $parts[] = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
        }
        if ($diff->d) {
            $parts[] = $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
        }
        if ($diff->h) {
            $parts[] = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
        }
        if ($diff->i) {
            $parts[] = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
        if ($diff->s) {
            $parts[] = $diff->s . ' second' . ($diff->s > 1 ? 's' : '');
        }

        return implode(' ', array_slice($parts, 0, $maxParts));
    }

    /**
     * Convert HTML to Markdown using XSLT.
     * 
     * Example:
     * 
     * $md = $api->convert->htmlToMarkdown("<h1>Hello</h1><p>This is <b>bold</b> text.</p>");
     *
     * @param string|DOMDocument|HTMLDocument $html
     * @return string|null
     */
    public function htmlToMarkdown(string|DOMDocument|HTMLDocument $html): ?string
    {
        global $api;

        if (is_string($html)) {
            $doc = new DOMDocument();
            $doc->substituteEntities = false;
            $doc->strictErrorChecking = false;
            $doc->recover = true;
            $doc->formatOutput = false;
            $doc->resolveExternals = false;
            $doc->validateOnParse = false;
            $doc->xmlStandalone = true;        
            @$doc->loadHTML('<!DOCTYPE html>'.PHP_EOL.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        } else {
            $doc = $html;
        }

        if (!$doc->documentElement) {
            throw new \Exception("Failed to parse HTML: $html");
        }

        $xsl = new \DOMDocument();
        $xsl->load("module://zolinga-commons/data/html2md.xsl");

        $proc = new \XSLTProcessor();
        $proc->importStylesheet($xsl);

        $md = $proc->transformToXML($doc);
        return trim($md ?: '') ?: null;
    }

    /**
     * Convert currency amount from one currency to another.
     * 
     * Note: This is a stub function. Actual implementation would require
     * integration with a currency conversion API or service.
     *
     * @param int $amount Amount in cents (e.g. 280 for $2.80)
     * @param string $fromCurrency Currency code of the input amount (e.g. 'USD')
     * @param string $toCurrency Currency code to convert to (e.g. 'EUR')
     * @return int Converted amount in cents
     */
    public function convertCurrency(int $amount, string $fromCurrency, string $toCurrency): int
    {
        global $api;
        return $api->currency->convert($amount, $fromCurrency, $toCurrency);
    }
}
