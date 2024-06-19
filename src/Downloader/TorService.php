<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader;

use Zolinga\Commons\Downloader\DownloaderService;
use Zolinga\Commons\Downloader\Exception\GotNothingException;
use Zolinga\Commons\Downloader\Exception\SslException;
use Zolinga\Commons\Downloader\Exception\TimeoutException;

/**
 * Tor Service API
 * 
 * It expects the Tor to be running with SOCKS5 proxy on on the address specified 
 * in config's tor.proxy.host and tor.proxy.port (default 127.0.0.1:9050)
 * 
 * In order to use control port you need to specify the password in config's tor.control.password
 * alongside with the address and port (default 127.0.0.1:9051)
 * 
 * @property-read int $requestCounter Number of requests made since the last identity refresh.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-06-13
 */
class TorService extends Anonymizer
{
    private ?string $controlHost = null;
    private ?int $controlPort = null;
    private ?string $controlPassword = null;
    private mixed $controlFilePointer = null;

    // @experimental list of public tor nodes
    private array $publicExitNodeList = [];
    private int $publicExitNodeListUpdate = 0;

    public function __construct(string $downloaderName = 'tor')
    {
        global $api;
        parent::__construct($downloaderName);

        $socksHost = $api->config['tor']['proxy']['host']
            or throw new \Exception("Tor proxy host not set in config.");
        $socksPort = $api->config['tor']['proxy']['port']
            or throw new \Exception("Tor proxy port not set in config.");

        $this->setProxy($socksHost, $socksPort);
        $this->setControl($api->config['tor']['control']['host'], $api->config['tor']['control']['port'], $api->config['tor']['control']['password']);

        $api->log->info($this->downloaderName, "Tor proxy set to $socksHost:$socksPort (control port: {$this->controlHost}:{$this->controlPort})");
    }

    // Experimental feature - guess if the IP is a public Tor node
    private function matchPublicExitNodeList(string $ip): bool
    {
        if ($this->publicExitNodeListUpdate < time() - 600) {
            $list = file_get_contents('https://openinternet.io/tor/tor-exit-list.txt');
            $this->publicExitNodeList = explode("\n", $list);
            $this->publicExitNodeListUpdate = time();
            // Filter valid IPs only
            $this->publicExitNodeList = array_filter($this->publicExitNodeList, function ($ip) {
                return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
            });

            // $this->controlConnect();
            // // $this->controlCommand("SETCONF excludeExitNod   es=\"1.1.1.1,2.2.2.2\"");
            // $this->controlCommand("SETCONF excludeExitNodes=\"".implode(',', $this->publicExitNodeList)."\"");
            // $this->controlDisconnect();
        }

        return in_array($ip, $this->publicExitNodeList);
    }

    /**
     * Set the control port settings for the Tor service.
     *
     * @param string $host
     * @param integer $port
     * @param string $password
     * @return void
     */
    public function setControl(string $host, int $port, string $password): void
    {
        $this->controlHost = $host;
        $this->controlPort = $port;
        $this->controlPassword = $password;
    }

    /**
     * Check if the control port settings are configured.
     *
     * @return bool
     */
    private function isControlConfigured(): bool
    {
        return $this->controlHost && $this->controlPort && $this->controlPassword !== null;
    }

    /**
     * Refreshes the identity of the Tor service by authenticating with the control port and sending a NEWNYM signal.
     * 
     * Control Port must be configured and settings must be set in the config's tor.control section.
     *
     * @throws \InvalidArgumentException If the Tor control host, port, or password is not set in the configuration.
     * @throws \Exception If there is an error connecting to the Tor control port, authenticating, or sending the NEWNYM signal.
     * @return void
     */
    public function changeExitNode(): void
    {
        global $api;

        $this->qos->setExitNode('*TOR Identity Pings*'); // pings to find out IP address
        $info = [];

        do {
            $this->sendNewNymSignal();
            $ip = $this->getMyIP();
            $stats = $this->qos->getStats($ip);

            if ($ip === $this->lastIP) {
                $api->log->warning($this->downloaderName, "Failed to change IP address ($ip). Retrying... (" . json_encode($stats) . ")");
                $retry = true;
            } elseif ($stats['total'] >= 3 && !$stats['successRatio']) {
                $api->log->warning($this->downloaderName, "IP address $ip is dysfunctional. Retrying... (" . json_encode($stats) . ")");
                $retry = true;
            } elseif ($this->matchPublicExitNodeList($ip)) {
                // $api->log->warning($this->downloaderName, "IP address $ip is a listed public Tor node. Retrying... (" . json_encode($stats) . ")");
                // $retry = true;
                $info[] = "publicly listed node 😞";
                $retry = false;
            } else {
                $retry = false;
            }
        } while ($retry);

        $this->lastIP = $ip;
        $info[] = "new IP {$ip}";
        $this->qos->setExitNode($ip);
        $api->log->info($this->downloaderName, "Refreshed TOR circuit (" . implode(', ', $info) . ")... (" . json_encode($stats) . ")");

        parent::changeExitNode();
    }

    /**
     * Sends a NEWNYM signal to the Tor control port to request a new identity.
     *
     * @return void
     */
    private function sendNewNymSignal(): void
    {
        global $api;
        static $lastSignal = 0;

        $last = $lastSignal ? time() - $lastSignal : 10;
        sleep(max(0, 10 - $last));
        $lastSignal = time();

        if (!$this->isControlConfigured()) {
            $this->controlHost
                or throw new \InvalidArgumentException("Tor control host not set in config key tor.control.host");
            $this->controlPort
                or throw new \InvalidArgumentException("Tor control port not set in config key tor.control.port");
            $this->controlPassword
                or throw new \InvalidArgumentException("Tor control password not set in config key tor.control.password");
        }

        $this->controlConnect();
        $this->controlCommand("SIGNAL NEWNYM");
        
        // Get new IP
        // $reportedIp = $this->controlCommand("GETINFO address"); // does not work most of the time
        // $ip = explode(' ', $reportedIp)[1] ?? null;
        // $api->log->info($this->downloaderName, "New reported exit node IP address: $reportedIp");
        // dormant

        $this->controlDisconnect();
    }

    private function controlConnect(): void {
        if (!$this->isControlConfigured()) {
            throw new \InvalidArgumentException("Tor control host and port not set.");
        }

        $this->controlFilePointer = fsockopen($this->controlHost, $this->controlPort, $errno, $errstr, 30)
            or throw new \Exception("Failed to connect to Tor control port {$this->controlHost}:{$this->controlPort}: $errstr ($errno)");

        stream_set_timeout($this->controlFilePointer, 30);
        $this->controlCommand("AUTHENTICATE \"$this->controlPassword\"");
    }

    private function controlDisconnect(): void  {
        $this->controlCommand("QUIT");
        fclose($this->controlFilePointer);
    }

    private function controlCommand(string $command): ?string
    {
        fwrite($this->controlFilePointer, "$command\r\n");
        $response = trim(fread($this->controlFilePointer, 1024));
        // 551 is when "GETINFO address" is not available
        if (strpos($response, "250") !== 0 && strpos($response, "551") !== 0) {
            throw new \Exception("Failed to send $command command to Tor control port: $response");
        }
        return $response ?? null;
    }
}
