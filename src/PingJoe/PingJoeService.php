<?php

declare(strict_types=1);

namespace Zolinga\Commons\PingJoe;

use Zolinga\System\Events\ServiceInterface;

/**
 * Service for integration with pingjoe.com monitoring service.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-04-30
 */
class PingJoeService implements ServiceInterface
{

    public function ping(string $spotter, string|int $nextCheck): void
    {
        global $api;

        if (!preg_match("/^[a-z0-9._+:-]+$/i", $spotter)) {
            throw new \Exception("Invalid spotter name: $spotter, only alphanumeric characters and -:._+ allowed.");
        }

        // Send a ping to the monitoring service
        $user = $api->config['pingjoe']['user']
            or throw new \Exception("Missing pingjoe user in config.");

        $password = $api->config['pingjoe']['password']
            or throw new \Exception("Missing pingjoe password in config.");


        $nextCheck = (is_string($nextCheck) && !is_numeric($nextCheck) ? strtotime($nextCheck) : intval($nextCheck))
            or throw new \Exception("Invalid nextCheck value: $nextCheck");

        $url = "https://$user:$password@api.pingjoe.com/$spotter/$nextCheck";
        
        if (file_get_contents($url)) {
            $api->log->info("pingjoe", "Pinged Joe: $url");
        } else {
            $api->log->error("pingjoe", "Failed to ping Joe: $url");
        }
    }
}
