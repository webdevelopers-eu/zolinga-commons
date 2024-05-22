<?php

declare(strict_types=1);

namespace Zolinga\Commons\Uploader;

/**
 * Represents an upload record.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-05-22
 */
class Upload {
    public readonly int $id;
    public readonly string $uri;
    public readonly string $name;
    public readonly string $data;
    public readonly string $mime;
    public readonly int $stamp;
    public readonly int $size;
    public readonly string $ip;

    public function __construct(int $id, string $uri, string $data, string $name, string $mime, int $stamp, int $size, string $ip)
    {
        $this->id = $id;
        $this->uri = $uri;
        $this->data = $data;
        $this->name = $name;
        $this->mime = $mime;
        $this->stamp = $stamp;
        $this->size = $size;
        $this->ip = $ip;
    }

    public function toArray(): array
    {
        return [
            "id" => $this->id,
            "uri" => $this->uri,
            "name" => $this->name,
            "mime" => $this->mime,
            "stamp" => $this->stamp,
            "size" => $this->size,
            "ip" => $this->ip
        ];
    }
}