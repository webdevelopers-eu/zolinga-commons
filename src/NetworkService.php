<?php

declare(strict_types=1);

namespace Zolinga\Commons;

use Zolinga\System\Events\ServiceInterface;

/**
 * Network helpers.
 *
 * Available as $api->network
 */
class NetworkService implements ServiceInterface
{
    /**
     * Check whether an IP address matches at least one another IP or CIDR range.
     *
     * Examples:
     *
     * $api->network->matchCidr('192.168.1.10', '192.168.1.0/24'); // true
     * $api->network->matchCidr('2001:db8::1', '2001:db8::/32'); // true
     * $api->network->matchCidr('10.0.0.1', '10.0.0.1'); // true
     * $api->network->matchCidr('10.0.0.1', '10.0.0.1/24'); // true
     *
     * Mutliple CIDR example:
     * $api->network->matchCidr('192.168.1.10', ['192.168.1.0/24', '10.0.0.0/8']); // true
     * 
     * @param string $ip IP address to test.
     * @param string|array $cidr Exact IP or CIDR notation. It can either a string or array of cidr strings.
     * @return bool
     */
    public function matchCidr(string $ip, string|array $cidr): bool
    {
        if (is_array($cidr)) {
            foreach ($cidr as $c) {
                if ($this->matchCidr($ip, $c)) {
                    return true;
                }
            }
            return false;
        }

        $ipBinary = @inet_pton($ip);
        if ($ipBinary === false) {
            return false;
        }

        if (!str_contains($cidr, '/')) {
            $cidrBinary = @inet_pton($cidr);
            return $cidrBinary !== false && $ipBinary === $cidrBinary;
        }

        [$networkAddress, $prefixRaw] = explode('/', $cidr, 2);
        if ($prefixRaw === '' || !ctype_digit($prefixRaw)) {
            return false;
        }

        $networkBinary = @inet_pton($networkAddress);
        if ($networkBinary === false || strlen($networkBinary) !== strlen($ipBinary)) {
            return false;
        }

        $prefixLength = (int) $prefixRaw;
        $maxBits = strlen($ipBinary) * 8;
        if ($prefixLength < 0 || $prefixLength > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($prefixLength, 8);
        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($networkBinary, 0, $fullBytes)) {
            return false;
        }

        $remainingBits = $prefixLength % 8;
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        $ipByte = ord($ipBinary[$fullBytes]);
        $networkByte = ord($networkBinary[$fullBytes]);

        return ($ipByte & $mask) === ($networkByte & $mask);
    }
}