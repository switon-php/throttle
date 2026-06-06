<?php

declare(strict_types=1);

namespace Switon\Throttle\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Core\ContainerInterface;
use Switon\Throttle\RateLimiterInterface;
use Switon\Throttle\ServiceProvider;
use Switon\Throttle\Tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ServiceProviderTest extends TestCase
{
    public function testRegisterDoesNotModifyContainer(): void
    {
        $provider = new ServiceProvider();
        $container = $this->createMock(ContainerInterface::class);

        $provider->register($container);

        $this->addToAssertionCount(1);
    }

    public function testBootCallsRateLimiterBoot(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects($this->once())->method('boot');

        $this->container->replace(RateLimiterInterface::class, $rateLimiter);
        $provider = $this->make(ServiceProvider::class);
        $provider->boot();
    }
}
