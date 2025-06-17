<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader\Exception;


class NotFoundException extends HttpErrorResponseException
{
    public function __construct(string $message = "Resource not found", int $httpCode = 404, ?\Throwable $previous = null,  ?string $httpResponseBody = null)
    {
        parent::__construct($message,  $httpCode, $previous, $httpResponseBody);
    }
    
}

