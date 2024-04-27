<?php

declare(strict_types=1);

namespace Zolinga\Commons;

use Zolinga\System\Events\ServiceInterface;
use NumberFormatter;


/**
 * Various formatting functions.
 */
class convertService implements ServiceInterface
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
}
