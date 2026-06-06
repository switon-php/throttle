<?php

declare(strict_types=1);

namespace Switon\Throttle\Attribute;

use Attribute;

use function is_string;

/**
 * Attribute that configures controller or action rate limits.
 *
 * Use when endpoints need declarative limits such as `10/m` or combined windows like `['10/m', '100/h']`.
 *
 * Road-signs:
 * - class-level defaults
 * - method-level overrides
 * - burst only applies to the first limit window
 *
 * @see \Switon\Throttle\RateLimiter::getRateLimit()
 * @see \Switon\Throttle\RateLimiterInterface
 * @see \Switon\Throttle\Exception\RateLimitExceededException
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class RateLimit
{
    /** @var list<string> */
    public array $limits;
    public ?int $burst;

    /**
     * @param string|list<string> $limits Limit specification(s) in `limit/period` format (for example: "10/m", "100/h")
     * @param int|null $burst Burst capacity for the first limit window
     */
    public function __construct(string|array $limits, ?int $burst = null)
    {
        $this->limits = is_string($limits) ? [$limits] : $limits;
        $this->burst = $burst;
    }
}
