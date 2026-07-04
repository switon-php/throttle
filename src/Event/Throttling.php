<?php

declare(strict_types=1);

namespace Switon\Throttle\Event;

use Switon\Core\SenderAwareInterface;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Throttle\RateLimiterInterface;

/**
 * Event emitted before applying one rate-limit window.
 *
 * Log category: `switon.throttle.throttling`
 *
 * Guidance: Event only carries throttling metadata; resolve request/user context in listeners.
 *
 * @see \Switon\Throttle\RateLimiterInterface
 * @see \Switon\Throttle\RateLimiter
 */
#[EventLevel(Severity::DEBUG)]
class Throttling implements SenderAwareInterface
{
    /**
     * @param string $key Throttle storage key
     * @param int $limit Window request limit
     * @param int $period Window length in seconds
     * @param int|null $burst Optional burst allowance for the first window
     */
    public function __construct(
        public RateLimiterInterface $sender,
        public string $key,
        public int    $limit,
        public int    $period,
        public ?int   $burst
    ) {
    }

    public function getSender(): RateLimiterInterface
    {
        return $this->sender;
    }
}
