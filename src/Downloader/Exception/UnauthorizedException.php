<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader\Exception;


class UnauthorizedException extends HttpErrorResponseException
{
    public function __construct(string $message = "Unauthorized", int $httpCode = 401, ?\Throwable $previous = null, ?string $httpResponseBody = null)
    {
        parent::__construct($message, $httpCode, $previous, $httpResponseBody);
    }
    
}
