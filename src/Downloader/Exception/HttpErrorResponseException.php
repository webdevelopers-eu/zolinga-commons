<?php
declare(strict_types=1);

namespace Zolinga\Commons\Downloader\Exception;
use Exception;

/**
 * CURL SSL Exception
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-06-13
 */
class HttpErrorResponseException extends DownloaderException {
    /**
     * Constructor.
     *
     * @param int $httpCode The HTTP status code.
     * @param string $responseBody The HTTP response body.
     */
    public function __construct(
        string $message, 
        public readonly int $httpCode, 
        ?\Throwable $previous = null, 
        public readonly ?string $httpResponseBody = null)
    {
        parent::__construct($message, $httpCode + 80000, $previous);
    }
}