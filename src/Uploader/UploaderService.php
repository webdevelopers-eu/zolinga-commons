<?php

declare(strict_types=1);

namespace Zolinga\Commons\Uploader;

use Zolinga\System\Events\{ServiceInterface, RequestResponseEvent};

/**
 * Server side component for <file-upload> web component
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-05-22
 */
class UploaderService implements ServiceInterface
{
    /**
     * Upload file to the database and return unique file URI
     * 
     * 
     * @param string $file path to file
     * @param string $name name of the file
     * @param string $mime MIME type of the file
     * @return string unique file URI
     */
    public function upload(string $file, string $name, string $mime)
    {
        global $api;


        $sha1 = sha1(microtime(true) . $file);

        // Columns:
        // id	int(10) UN AI PK
        // ip	varchar(39)
        // mime	varchar(255)
        // stamp	int(10) UN
        // name	varchar(1024)
        // data	mediumblob
        // hash	binary(20)
        $api->db->query(<<<SQL
            INSERT INTO
                uploads (ip, mime, stamp, name, data, size, hash)
            VALUES
                (?, ?, ?, ?, ?, ?, UNHEX(?))
        SQL, $_SERVER['REMOTE_ADDR'], $mime, time(), $name, file_get_contents($file), filesize($file), $sha1);

        $this->cleanUp();

        return $this->hash2uri($sha1);
    }

    public function onUploader(RequestResponseEvent $event)
    {
        switch ($event->request['op']) {
            case "remove":
                $this->remove($event->request['uri']);
                break;
        }
    }
    
    public function remove(string $uri)
    {
        global $api;
        $hash = $this->uri2hash($uri);
        $api->db->query("DELETE FROM uploads WHERE hash = UNHEX(?) LIMIT 1", $hash);
    }


    public function getUpload(string $uri): Upload {
        global $api;
        $hash = $this->uri2hash($uri);
        $data = $api->db->query("SELECT * FROM uploads WHERE hash = UNHEX(?) LIMIT 1", $hash)->fetchAssoc();
        if (!$data) {
            throw new \Exception("Upload not found: $uri");
        }
        return new Upload($data['id'], $uri, $data['data'], $data['name'], $data['mime'], $data['stamp'], $data['size'], $data['ip']);
    }

    public function uri2hash(string $uri)
    {
        $prefix = 'zolinga:uploader:';
        if (!preg_match("/^{$prefix}[0-9a-f]{40}$/", $uri)) {
            throw new \InvalidArgumentException("Invalid Uploader URI: $uri");
        }
        return substr($uri, strlen($prefix), 40);
    }

    private function hash2uri(string $hash)
    {
        return "zolinga:uploader:$hash";
    }

    private function cleanUp()
    {
        global $api;
        $api->db->query("DELETE FROM uploads WHERE stamp < ?", time() - 3600 * 6);
    }
}