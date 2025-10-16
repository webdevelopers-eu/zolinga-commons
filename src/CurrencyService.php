<?php

declare(strict_types=1);

namespace Zolinga\Commons;

use Dom\HTMLDocument;
use DOMDocument;
use Zolinga\System\Events\ServiceInterface;
use NumberFormatter;


/**
 * Currency related functions.
 * 
 * Available as $api->currency
 */
class CurrencyService implements ServiceInterface
{
    public const CURRENCY_PRECISION_INTERNAL = 2; // Number of decimal places for currency formatting
    public const CURRENCY_PRECISION_DISPLAY = 2; // Number of decimal places for display purposes
    private const EXCHANGE_RATES_API_URL = 'http://www.floatrates.com/daily/usd.xml';
    private const EXCHANGE_RATES_CACHE_FILE = 'private://zolinga-commons/exchange_rates.json';
    private const EXCHANGE_RATES_CACHE_TTL = 86400; // 24 hours

    private ?array $exchangeRatesCache = null;
    public array $exchangeRates {
        get {
            if ($this->exchangeRatesCache === null) {
                $this->loadExchangeRates();
            }
            return $this->exchangeRatesCache;
        }
    }

    /**
     * Format currency amount to a human-readable, locale aware string.
     * 
     * For now we support only USD currency.
     *
     * @param int $amount Amount in cents (e.g. 280 for $2.80)
     * @return string e.g. "$2.80"
     */
    public function format(int $amount, string $currency = 'USD'): string 
    {
        global $api;

        $currency = strtoupper($currency);
        
        // Format currency to 2 decimal places with comma as decimal separator
        $fmt = new NumberFormatter($api->locale->locale, NumberFormatter::CURRENCY);
        $fmt->setAttribute(NumberFormatter::FRACTION_DIGITS, self::CURRENCY_PRECISION_DISPLAY);
        $fmt->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currency);

        $formatted = $fmt->formatCurrency(floatval($amount / (10 ** self::CURRENCY_PRECISION_INTERNAL)), $currency);
        if ($formatted === false) {
            throw new \RuntimeException("Failed to format currency ($currency): {$amount}");
        }
        return $formatted;
    }
    
    /**
     * Convert currency amount from one currency to another.
     * 
     * Note: This is a stub function. Actual implementation would require
     * integration with a currency conversion API or service.
     * 
     * To convert 20 CZK to PLN: $converted = $api->currency->convert(2000, 'CZK', 'PLN');
     *
     * @param int $amount Amount in cents (e.g. 280 for $2.80)
     * @param string $fromCurrency Currency code of the input amount (e.g. 'USD')
     * @param string $toCurrency Currency code to convert to (e.g. 'EUR')
     * @return int Converted amount in cents
     */
    public function convert(int $amount, string $fromCurrency, string $toCurrency): int
    {
        if (!isset($this->exchangeRates[$fromCurrency]) || !isset($this->exchangeRates[$toCurrency])) {
            throw new \InvalidArgumentException("Unsupported currency conversion: $fromCurrency to $toCurrency");
        }

        $ret = $amount / $this->exchangeRates[$fromCurrency] * $this->exchangeRates[$toCurrency];

        return (int) $ret;
    }

    /**
     * Load exchange rates from external API and cache them locally.
     * 
     * This method populates $this->exchangeRatesCache with the latest exchange rates.
     */
    private function loadExchangeRates(): void
    {
        global $api;

        if (!file_exists(self::EXCHANGE_RATES_CACHE_FILE) || (time() - filemtime(self::EXCHANGE_RATES_CACHE_FILE) < self::EXCHANGE_RATES_CACHE_TTL)) {
            $xml = @file_get_contents(self::EXCHANGE_RATES_API_URL);
            if ($xml) {
                $doc = new \DOMDocument();
                if ($doc->loadXML($xml)) {
                    $xp = new \DOMXPath($doc);
                    $rates = [];
                    foreach ($xp->query('//item') as $item) {
                        $baseCurrency = $xp->evaluate('string(./baseCurrency)', $item);
                        if ($baseCurrency !== 'USD') {
                            $api->log->warning('currency', "Skipping unsupported base currency: $baseCurrency: " . $doc->saveXML($item));
                            continue; // we only support USD as base currency for now
                        }
                        $targetCurrency = $xp->evaluate('string(./targetCurrency)', $item);
                        $rate = (float)$xp->evaluate('string(./exchangeRate)', $item);
                        $rates[$targetCurrency] = $rate;
                    }
                    $api->log->info('currency', "Loaded fresh exchange rates.");
                } else {
                    $api->log->error('currency', "Failed to parse exchange rates from URL: " . self::EXCHANGE_RATES_API_URL . " Response: $xml");
                }

                if (!empty($rates)) {
                    $rates['USD'] = 1.0; // add USD to USD rate
                    ksort($rates);
                    $this->exchangeRatesCache = $rates;
                    @file_put_contents(self::EXCHANGE_RATES_CACHE_FILE, json_encode($rates));
                    return;
                } else { // otherwise load old cache if any
                    $api->log->error('currency', "No exchange rates found from URL: " . self::EXCHANGE_RATES_API_URL . " Response: $xml");
                }
            }
        }

        // Load from cache
        $this->exchangeRatesCache = json_decode(file_get_contents(self::EXCHANGE_RATES_CACHE_FILE), true);
        if (!is_array($this->exchangeRatesCache)) {
            throw new \RuntimeException("Failed to load exchange rates cache");
        }
    }
}