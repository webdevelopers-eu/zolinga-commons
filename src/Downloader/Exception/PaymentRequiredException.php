<?php

declare(strict_types=1);

namespace Zolinga\Commons\Downloader\Exception;


class PaymentRequiredException extends HttpErrorResponseException
{
    public function __construct(string $message = "Payment Required", int $httpCode = 402, ?\Throwable $previous = null, ?string $httpResponseBody = null)
    {
        parent::__construct($message, $httpCode, $previous, $httpResponseBody);
    }
    
}
