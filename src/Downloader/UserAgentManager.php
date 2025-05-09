<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader;

use Exception;

class UserAgentManager
{
    private const UA_HEADERS_PATH = 'module://zolinga-commons/data/ua-headers.json';

    public function __construct()
    {
        // Constructor is intentionally empty as per requirements.
        // Dependencies like $api->log and $api->fs will be accessed via global $api.
    }

    public function getRandomUserAgentData(): array
    {
        global $api;
        
        $uaHeadersJsonPath = $api->fs->toPath(self::UA_HEADERS_PATH);
        if (!file_exists($uaHeadersJsonPath)) {
            throw new Exception("User agent headers file not found at " . self::UA_HEADERS_PATH . " (resolved: " . $uaHeadersJsonPath . ")");
        }

        $jsonContent = file_get_contents($uaHeadersJsonPath);
        if ($jsonContent === false) {
            throw new Exception("Failed to read user agent headers file from " . self::UA_HEADERS_PATH);
        }

        $list = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to decode user agent headers from " . self::UA_HEADERS_PATH . ": " . json_last_error_msg());
        }
        
        if (empty($list)) {
            throw new Exception("User agent list is empty in " . self::UA_HEADERS_PATH);
        }

        $info = $list[array_rand($list)];
        $headers = $info['headers'] ?? []; // Base headers from the selected profile
        
        // Apply randomizers if they exist
        if (isset($info['randomizers']) && is_array($info['randomizers'])) {
            $replaceVals = [];
            foreach($info['randomizers'] as $key => $v) { // Iterate with key for ${key} replacement
                 if (!is_array($v) || count($v) < 1) {
                     $replaceVals[$key] = ''; // Default to empty string for malformed randomizers
                     continue;
                 }
                switch($v[0]) {
                    case 'random':
                        $replaceVals[$key] = (isset($v[1], $v[2])) ? rand((int)$v[1], (int)$v[2]) : '';
                        break;
                    default:
                        // Log unsupported randomizer types
                        $api->log->warning("UserAgentManager", "Unsupported randomizer type '{$v[0]}' in user agent profile.");
                        $replaceVals[$key] = ''; // Default for unsupported types
                }
            }

            $replaceWhat = array_map(fn ($k) => '${' . $k . '}', array_keys($replaceVals));
            
            $processedHeaders = [];
            foreach ($headers as $headerKey => $headerValue) {
                $processedHeaders[$headerKey] = str_replace($replaceWhat, array_values($replaceVals), $headerValue);
            }
            $headers = $processedHeaders;
        }
        
        $userAgent = $headers['User-Agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'; // Default fallback
        
        // Remove User-Agent from the headers array as it's typically set via CURLOPT_USERAGENT
        // All other headers (like Sec-CH-UA, etc.) remain.
        unset($headers['User-Agent']);

        $api->log->info("UserAgentManager", "Selected User-Agent: {$userAgent}");

        return [
            'userAgent' => $userAgent,
            'headers' => $headers, // These are additional headers to be merged
        ];
    }
}
