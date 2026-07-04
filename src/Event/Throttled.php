<?php

declare(strict_types=1);

namespace Switon\Throttle\Event;

use Switon\Core\SenderAwareInterface;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Throttle\RateLimiterInterface;

/**
 * Event emitted when rate-limit is exceeded.
 *
 * Log category: `switon.throttle.throttled`
 *
 * Guidance: Event only carries exceeded-limit metadata; resolve request/user context in listeners.
 *
 * @see \Switon\Throttle\RateLimiterInterface
 * @see \Switon\Throttle\RateLimiter
 * @see \Switon\Throttle\Exception\RateLimitExceededException
 */
#[EventLevel(Severity::INFO)]
class Throttled implements SenderAwareInterface
{
    /**
     * @param string $key Throttle storage key
     * @param int $limit Window request limit
     * @param int $period Window length in seconds
     * @param int $count Observed request count when the limit was exceeded
     * @param int|null $burst Optional burst allowance for the first window
     */
    public function __construct(
        public RateLimiterInterface $sender,
        public string $key,
        public int    $limit,
        public int    $period,
        public int    $count,
        public ?int   $burst
    ) {
    }

    public function getSender(): RateLimiterInterface
    {
        return $this->sender;
    }
}
