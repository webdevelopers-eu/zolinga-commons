<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader\Exception;


class BadRequestException extends HttpErrorResponseException
{
    public function __construct(string $message = "Bad Request", int $httpCode = 400, ?\Throwable $previous = null, ?string $httpResponseBody = null)
    {
        parent::__construct($message, $httpCode, $previous, $httpResponseBody);
    }
    
}
