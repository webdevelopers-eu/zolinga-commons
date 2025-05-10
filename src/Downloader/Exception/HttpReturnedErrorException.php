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
class HttpReturnedErrorException extends DownloaderException {
    /**
     * The HTTP status code.
     *
     * @var int
     */
    protected int $httpCode;

    /**
     * The HTTP response body.
     *
     * @var string
     */
    protected string $responseBody;

    /**
     * Constructor.
     *
     * @param int $httpCode The HTTP status code.
     * @param string $responseBody The HTTP response body.
     */
    public function __construct(int $httpCode, string $responseBody) {
        parent::__construct("HTTP error: $httpCode");
        $this->httpCode = $httpCode;
        $this->responseBody = $responseBody;
    }
    /**
     * Get the HTTP status code.
     *
     * @return int The HTTP status code.
     */
    public function getHttpCode(): int {
        return $this->httpCode;
    }
    /**
     * Get the HTTP response body.
     *
     * @return string The HTTP response body.
     */
    public function getResponseBody(): string {
        return $this->responseBody;
    }
}