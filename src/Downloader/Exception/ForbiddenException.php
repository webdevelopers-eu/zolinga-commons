<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader\Exception;


class ForbiddenException extends HttpErrorResponseException
{
    public function __construct(string $message = "Forbidden", int $httpCode = 403, ?\Throwable $previous = null, ?string $httpResponseBody = null)
    {
        parent::__construct($message, $httpCode, $previous, $httpResponseBody);
    }
    
}

