<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader;

/**
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-06-17
 */
class QoS
{
    private array $stats = [];
    private ?string $exitNode = null;
    private const STATS_PROTO = [
        'total' => 0,
        'success' => 0,
        'failure' => 0,
        'time' => 0,
        'speed' => 0,
        'size' => 0,
        'lastStatus' => 'success',
        'lastStatusCount' => 0,
        'successRatio' => 1, // 'success' / 'total'
        'errors' => [],
    ];

    public function __construct()
    {
        $this->setExitNode();
    }

    public function isDysfunctional(?string $exitNode = null): bool
    {
        $stats = $this->getStats($exitNode);
        return $stats['total'] >= 3 && !$stats['successRatio'];
        // return $this->getStats()['lastStatusCount'] > 3 && $this->getStats()['lastStatus'] === 'failure';
    }

    public function getStats(?string $exitNode = null): array
    {
        return $this->stats[$exitNode ?? $this->exitNode] ?? self::STATS_PROTO;
    }

    /**
     * @param string $exitNode The exit node identifier - default is 'default', usually IP address
     * @return string current exit node identifier
     */
    public function setExitNode(string $exitNode = 'default'): string
    {
        $this->exitNode = $exitNode;
        if (!isset($this->stats[$exitNode])) {
            $this->stats[$exitNode] = self::STATS_PROTO;
        }
        return $this->exitNode;
    }

    /**
     * @return string current exit node identifier
     */
    public function getExitNode(): string
    {
        return $this->exitNode;
    }

    public function addSuccess(float $time, int $size): void
    {
        $this->stats[$this->exitNode]['time'] += $time;
        $this->stats[$this->exitNode]['size'] += $size;
        $this->stats[$this->exitNode]['speed'] = $this->stats[$this->exitNode]['size'] / $this->stats[$this->exitNode]['time'];
        $this->register('success');
    }

    public function addFailure(string $error): void
    {
        $this->stats[$this->exitNode]['errors'][$error] = ($this->stats[$this->exitNode]['errors'][$error] ?? 0) + 1;
        $this->register('failure');
    }

    private function register(string $status): void
    {
        $this->stats[$this->exitNode]['total']++;
        $this->stats[$this->exitNode][$status]++;
        $this->stats[$this->exitNode]['successRatio'] =
            $this->stats[$this->exitNode]['success'] / $this->stats[$this->exitNode]['total'];

        if ($this->stats[$this->exitNode]['lastStatus'] === $status) {
            $this->stats[$this->exitNode]['lastStatusCount']++;
        } else {
            $this->stats[$this->exitNode]['lastStatus'] = $status;
            $this->stats[$this->exitNode]['lastStatusCount'] = 1;
        }
    }
}
