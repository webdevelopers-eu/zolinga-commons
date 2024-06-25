<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader;

/**
 * This is the component used to track the download speed for a single host.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-06-25
 */
class ThrottlerDomain {
    /**
     * Host to match the throttler to.
     *
     * @var string
     */
    public readonly string $host;

    /**
     * List of recent download speeds.
     *
     * @var array
     */
    private array $history = [];

    /**
     * Maximum number of requests to be made in the given time frame.
     *
     * @var int
     */
    public readonly int $maxRequests;

    /**
     * Maximum number of seconds to be used for the given number of requests.
     *
     * @var int
     */
    public readonly int $maxSeconds;

    public function __construct(string $host, int $maxSeconds = 1, int $maxRequests = 1) {
        $this->host = strtolower($host);
        $this->maxRequests = $maxRequests;
        $this->maxSeconds = $maxSeconds;
        $this->history = array_fill(0, $maxRequests, 0);
    }

    /**
     * Check if the host matches the throttler.
     * 
     * E.g. 
     * 
     * Host "example.com" matches "example.com" and "sub.example.com"...
     *
     * @param string $host
     * @return bool
     */
    public function matchHost(string $host): bool {
        $host = strtolower($host);
        return str_ends_with($host, '.' . $this->host) !== false || $this->host === $host;
    }

    /**
     * Record the request time for the last request.
     *
     * @param ?integer $time The time of the request. If null, the current time is used.
     * @return void
     */
    public function recordRequest(?int $time = null): void {
        $this->history[] = $time ?? time();
        array_shift($this->history);
    }

    /**
     * Check if the requests to the host are over the limit.
     *
     * @return bool
     */
    public function isOverLimit(): bool {
        return !!$this->getRemainingTime();
    }

    /**
     * Get the remaining time until the next request can be made.
     *
     * @return int
     */
    public function getRemainingTime(): int {
        return max(0, $this->maxSeconds - (time() - ($this->history[0] ?? 0)));
    }
}