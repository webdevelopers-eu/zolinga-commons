<?php
declare(strict_types=1);

namespace Zolinga\Commons\Downloader\Exception;
use Exception;

/**
 * CURL Timeout Exception
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-06-13
 */
class TimeoutException extends Exception {}