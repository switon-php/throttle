<?php

declare(strict_types=1);

namespace Switon\Throttle\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted before applying one rate-limit window.
 *
 * Log category: throttle lifecycle.
 *
 * Guidance: Event only carries throttling metadata; resolve request/user context in listeners.
 *
 * @see \Switon\Throttle\RateLimiterInterface
 * @see \Switon\Throttle\RateLimiter
 */
#[EventLevel(Severity::DEBUG)]
class Throttling
{
    /**
     * @param string $key Throttle storage key
     * @param int $limit Window request limit
     * @param int $period Window length in seconds
     * @param int|null $burst Optional burst allowance for the first window
     */
    public function __construct(
        public string $key,
        public int    $limit,
        public int    $period,
        public ?int   $burst
    ) {
    }
}
