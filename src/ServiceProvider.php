<?php

declare(strict_types=1);

namespace Switon\Throttle;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ContainerInterface;
use Switon\Core\ServiceProviderInterface;

/**
 * Service provider that boots throttle support.
 *
 * Use when application startup should activate RateLimiterInterface::boot().
 *
 * Road-signs:
 * - register() relies on namespace auto-mapping
 * - boot() activates RateLimiterInterface::boot()
 *
 * @see \Switon\Core\ServiceProviderInterface
 */
class ServiceProvider implements ServiceProviderInterface
{
    #[Autowired] protected RateLimiterInterface $rateLimiter;

    /** {@inheritDoc} */
    public function register(ContainerInterface $container): void
    {
    }

    /** {@inheritDoc} */
    public function boot(): void
    {
        $this->rateLimiter->boot();
    }
}
