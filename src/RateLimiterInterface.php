<?php

declare(strict_types=1);

namespace Switon\Throttle;

use Switon\Throttle\Exception\RateLimitExceededException;

/**
 * Defines the rate-limiting contract for request throttling.
 *
 * Use when you need attribute-driven HTTP throttling or direct programmatic `hit()` checks.
 *
 * Road-signs:
 * - boot() registers request validation listener
 * - hit() applies one or more windows to a key
 * - RateLimit attribute drives controller/action configuration
 *
 * @see \Switon\Throttle\RateLimiter
 * @see \Switon\Throttle\Attribute\RateLimit
 * @see \Switon\Throttle\Exception\RateLimitExceededException
 */
interface RateLimiterInterface
{
    /**
     * Boots the rate limiter and registers request validation listener when enabled.
     */
    public function boot(): void;

    /**
     * Checks one or more rate limits for a key and throws when exceeded.
     *
     * @param string $key Unique identifier (for example: "sms:13800138000", "api:user123")
     * @param string|list<string> $limits Limit specification(s) in `limit/period` format (for example: "10/m", "100/h")
     * @param int|null $burst Burst capacity for the first limit
     *
     * @throws RateLimitExceededException
     */
    public function hit(string $key, string|array $limits, ?int $burst = null): void;
}
