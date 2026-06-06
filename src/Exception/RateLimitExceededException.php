<?php

declare(strict_types=1);

namespace Switon\Throttle\Exception;

use JetBrains\PhpStorm\ArrayShape;
use Switon\Throttle\Exception as BaseException;

/**
 * Exception for HTTP 429 rate-limit violations.
 *
 * Raised when a request exceeds configured throttling limits.
 * Fix: reduce request frequency, widen the window, or raise the configured limits.
 *
 * @see \Switon\Throttle\Exception
 * @see \Switon\Throttle\RateLimiter
 * @see \Switon\Throttle\RateLimiterInterface
 */
class RateLimitExceededException extends BaseException
{
    public function getStatusCode(): int
    {
        return 429;
    }

    #[ArrayShape(['code' => 'int', 'msg' => 'string'])]
    public function getJson(): array
    {
        return ['code' => 429, 'msg' => 'Too Many Requests'];
    }
}
