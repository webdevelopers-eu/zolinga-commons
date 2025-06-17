<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader\Exception;


class RequestTimeoutException extends HttpErrorResponseException
{
    public function __construct(string $message = "Request Timeout", int $httpCode = 408, ?\Throwable $previous = null, ?string $httpResponseBody = null)
    {
        parent::__construct($message, $httpCode, $previous, $httpResponseBody);
    }
    
}
