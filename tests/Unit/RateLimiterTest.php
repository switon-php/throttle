<?php

declare(strict_types=1);

namespace Switon\Throttle\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionMethod;
use Switon\Core\AppInterface;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Http\Event\RequestValidating;
use Switon\Http\RequestInterface;
use Switon\Principal\IdentityInterface;
use Switon\Redis\ClientInterface;
use Switon\Throttle\Attribute\RateLimit;
use Switon\Throttle\Exception\RateLimitExceededException;
use Switon\Throttle\RateLimiter;
use Switon\Throttle\Tests\Fixtures\MockRedisClient;
use Switon\Throttle\Tests\TestCase;

/**
 * Test cases for RateLimiter.
 *
 * Tests rate limiting functionality including burst capacity and multiple limits.
 */
#[AllowMockObjectsWithoutExpectations]
class RateLimiterTest extends TestCase
{
    protected RateLimiter $rateLimiter;
    protected MockRedisClient $mockRedis;
    protected AppInterface $mockApp;
    protected IdentityInterface $mockIdentity;
    protected RequestInterface $mockRequest;
    protected ListenerProviderInterface&MockObject $mockListenerProvider;
    protected EventDispatcherInterface&MockObject $mockEventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRedis = new MockRedisClient();
        $this->mockApp = $this->createMock(AppInterface::class);
        $this->mockIdentity = $this->createMock(IdentityInterface::class);
        $this->mockRequest = $this->createMock(RequestInterface::class);
        $this->mockListenerProvider = $this->createMock(ListenerProviderInterface::class);
        $this->mockEventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->mockApp->method('id')->willReturn('test-app');
        $this->rateLimiter = $this->makeRateLimiter();
    }

    /**
     * Test that boot() registers listener when enabled.
     */
    public function testBootRegistersListenerWhenEnabled(): void
    {
        $this->mockListenerProvider->expects($this->once())
            ->method('register')
            ->with($this->identicalTo($this->rateLimiter));

        $this->rateLimiter->boot();
    }

    /**
     * Test that boot() does not register listener when disabled.
     */
    public function testBootDoesNotRegisterWhenDisabled(): void
    {
        $rateLimiter = $this->makeRateLimiter(['enabled' => false]);

        $this->mockListenerProvider->expects($this->never())->method('register');

        $rateLimiter->boot();
    }

    /**
     * Test that onValidating() allows request when no RateLimit attribute is present.
     */
    public function testAllowsRequestWithoutRateLimitAttribute(): void
    {
        // Arrange
        $method = new ReflectionMethod(self::class, 'dummyMethodWithoutAttribute');
        $event = new RequestValidating($method);
        $rateLimiter = $this->makeRateLimiter();

        // Act & Assert - Should not throw exception
        $rateLimiter->onValidating($event);
        $this->assertTrue(true, 'Request should pass without RateLimit attribute');
    }

    /**
     * Test that onValidating() allows request when under rate limit.
     */
    public function testAllowsRequestUnderLimit(): void
    {
        // Arrange
        $method = new ReflectionMethod(self::class, 'dummyMethodWithRateLimit');
        $event = new RequestValidating($method);
        $rateLimiter = $this->makeRateLimiter();

        $this->mockIdentity->method('isGuest')->willReturn(true);
        $this->mockRequest->method('ip')->willReturn('192.168.1.1');

        // Act
        $rateLimiter->onValidating($event);

        // Assert
        $this->assertTrue(true, 'Request should pass when under limit');
        $this->assertCount(1, $this->mockRedis->calls['incr'], 'Should call incr once');
        $this->assertCount(1, $this->mockRedis->calls['expire'], 'Should set expiration');
    }

    /**
     * Test that onValidating() blocks request when rate limit exceeded.
     */
    public function testBlocksRequestWhenLimitExceeded(): void
    {
        // Arrange
        $method = new ReflectionMethod(self::class, 'dummyMethodWithRateLimit');
        $event = new RequestValidating($method);
        $rateLimiter = $this->makeRateLimiter();

        $this->mockIdentity->method('isGuest')->willReturn(true);
        $this->mockRequest->method('ip')->willReturn('192.168.1.1');

        // Simulate exceeding limit by pre-incrementing to 10
        $keyPattern = 'cache:test-app:rate_limit:' . self::class . ':dummyMethodWithRateLimit:192.168.1.1:10/60';
        for ($i = 0; $i < 10; $i++) {
            $this->mockRedis->incr($keyPattern);
        }

        // Assert
        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        // Act - Next request should exceed limit
        $rateLimiter->onValidating($event);
    }

    /**
     * Test that default limit applies when no RateLimit attribute and default is configured.
     */
    public function testAppliesDefaultWhenNoAttributeAndDefaultConfigured(): void
    {
        // Arrange
        $method = new ReflectionMethod(self::class, 'dummyMethodWithoutAttribute');
        $event = new RequestValidating($method);
        $rateLimiter = $this->makeRateLimiter(['limits' => ['10/m']]);

        $this->mockIdentity->method('isGuest')->willReturn(true);
        $this->mockRequest->method('ip')->willReturn('10.0.0.1');

        // Act
        $rateLimiter->onValidating($event);

        // Assert - Should apply default limit (call Redis incr)
        $expectedKeyPattern = 'cache:test-app:rate_limit:' . self::class . ':dummyMethodWithoutAttribute:10.0.0.1:10/60';
        $this->assertArrayHasKey('incr', $this->mockRedis->calls);
        $this->assertEquals($expectedKeyPattern, $this->mockRedis->calls['incr'][0]['key']);
    }

    /**
     * Test that default as array applies multiple limits (same format as attribute).
     */
    public function testAppliesDefaultArrayWhenNoAttribute(): void
    {
        // Arrange
        $method = new ReflectionMethod(self::class, 'dummyMethodWithoutAttribute');
        $event = new RequestValidating($method);
        $rateLimiter = $this->makeRateLimiter(['limits' => ['10/m', '100/h']]);

        $this->mockIdentity->method('isGuest')->willReturn(true);
        $this->mockRequest->method('ip')->willReturn('10.0.0.2');

        // Act
        $rateLimiter->onValidating($event);

        // Assert - Should call incr for both periods (60 and 3600)
        $this->assertArrayHasKey('incr', $this->mockRedis->calls);
        $this->assertCount(2, $this->mockRedis->calls['incr']);
    }

    /**
     * Test that default with burst applies when no RateLimit attribute.
     */
    public function testAppliesDefaultWithBurstWhenNoAttribute(): void
    {
        // Arrange
        $method = new ReflectionMethod(self::class, 'dummyMethodWithoutAttribute');
        $event = new RequestValidating($method);
        $rateLimiter = $this->makeRateLimiter(['limits' => ['100/m'], 'burst' => 20]);

        $this->mockIdentity->method('isGuest')->willReturn(true);
        $this->mockRequest->method('ip')->willReturn('10.0.0.3');

        // Act
        $rateLimiter->onValidating($event);

        // Assert - Should use burst logic (setex for first request)
        $this->assertArrayHasKey('setex', $this->mockRedis->calls);
    }

    /**
     * Test that no limit applies when no RateLimit attribute and limits is null.
     */
    public function testNoLimitWhenNoAttributeAndLimitsNull(): void
    {
        // Arrange
        $method = new ReflectionMethod(self::class, 'dummyMethodWithoutAttribute');
        $event = new RequestValidating($method);
        $rateLimiter = $this->makeRateLimiter(['limits' => null]);

        // Act
        $rateLimiter->onValidating($event);

        // Assert - Should not call Redis
        $this->assertArrayNotHasKey('incr', $this->mockRedis->calls);
    }

    /**
     * Test that class-level RateLimit attribute applies when method has no attribute.
     */
    public function testAppliesClassLevelRateLimitWhenMethodHasNoAttribute(): void
    {
        // Arrange
        $method = new ReflectionMethod(ClassLevelRateLimitController::class, 'indexAction');
        $event = new RequestValidating($method);
        $rateLimiter = $this->makeRateLimiter();
        $this->mockIdentity->method('isGuest')->willReturn(true);
        $this->mockRequest->method('ip')->willReturn('10.9.0.1');

        // Act
        $rateLimiter->onValidating($event);

        // Assert
        $expectedKey = 'cache:test-app:rate_limit:' . ClassLevelRateLimitController::class . ':indexAction:10.9.0.1:7/60';
        $this->assertSame($expectedKey, $this->mockRedis->calls['incr'][0]['key']);
    }

    /**
     * Test that method-level RateLimit overrides class-level RateLimit.
     */
    public function testMethodLevelRateLimitOverridesClassLevelRateLimit(): void
    {
        // Arrange
        $method = new ReflectionMethod(MethodOverridesClassRateLimitController::class, 'overrideAction');
        $event = new RequestValidating($method);
        $rateLimiter = $this->makeRateLimiter();
        $this->mockIdentity->method('isGuest')->willReturn(true);
        $this->mockRequest->method('ip')->willReturn('10.9.0.2');

        // Act
        $rateLimiter->onValidating($event);

        // Assert - method level is 2/m, class level is 9/m
        $expectedKey = 'cache:test-app:rate_limit:' . MethodOverridesClassRateLimitController::class . ':overrideAction:10.9.0.2:2/60';
        $this->assertSame($expectedKey, $this->mockRedis->calls['incr'][0]['key']);
    }

    /**
     * Test that rate limiting uses user ID when authenticated.
     */
    public function testUsesUserIdWhenAuthenticated(): void
    {
        // Arrange
        $method = new ReflectionMethod(self::class, 'dummyMethodWithRateLimit');
        $event = new RequestValidating($method);
        $rateLimiter = $this->makeRateLimiter();

        $this->mockIdentity->method('isGuest')->willReturn(false);
        $this->mockIdentity->method('getName')->willReturn('user123');

        // Act
        $rateLimiter->onValidating($event);

        // Assert - Verify key uses user ID instead of IP
        $expectedKeyPattern = 'cache:test-app:rate_limit:' . self::class . ':dummyMethodWithRateLimit:user123:10/60';
        $this->assertArrayHasKey('incr', $this->mockRedis->calls, 'Should call incr method');
        $this->assertEquals($expectedKeyPattern, $this->mockRedis->calls['incr'][0]['key'], 'Should use user ID in key');
    }

    /**
     * Test that hit() allows request when under limit.
     */
    public function testHitAllowsUnderLimit(): void
    {
        // Act
        $this->rateLimiter->hit('sms:13800138000', '5/m');

        // Assert
        $this->assertArrayHasKey('incr', $this->mockRedis->calls, 'Should call incr');
        $this->assertEquals('sms:13800138000:5/60', $this->mockRedis->calls['incr'][0]['key'], 'Should use correct cache key');
    }

    /**
     * Test that hit() blocks when limit exceeded.
     */
    public function testHitBlocksWhenExceeded(): void
    {
        // Arrange - Pre-fill to limit
        $cacheKey = 'sms:13800138000:5/60';
        for ($i = 0; $i < 5; $i++) {
            $this->mockRedis->incr($cacheKey);
        }

        // Assert
        $this->expectException(RateLimitExceededException::class);

        // Act
        $this->rateLimiter->hit('sms:13800138000', '5/m');
    }

    /**
     * Test that hit() supports multiple limits.
     */
    public function testHitMultipleLimits(): void
    {
        // Act
        $this->rateLimiter->hit('api:user1', ['10/m', '100/h']);

        // Assert
        $this->assertCount(2, $this->mockRedis->calls['incr'], 'Should call incr for each limit');
        $this->assertEquals('api:user1:10/60', $this->mockRedis->calls['incr'][0]['key']);
        $this->assertEquals('api:user1:100/3600', $this->mockRedis->calls['incr'][1]['key']);
    }

    /**
     * Test that hit() supports burst on first limit.
     */
    public function testHitWithBurst(): void
    {
        // Act
        $this->rateLimiter->hit('api:user1', '100/m', 20);

        // Assert - First request uses setex (burst branch)
        $this->assertArrayHasKey('setex', $this->mockRedis->calls, 'Should use setex for burst first request');
    }

    /**
     * Test that burst rejects when used count reaches hard limit.
     */
    public function testBurstRejectsWhenHardLimitReached(): void
    {
        // Arrange - Pre-fill to limit (100)
        $cacheKey = 'burst:test:100/60';
        $this->mockRedis->storage[$cacheKey] = 100;
        $this->mockRedis->ttl[$cacheKey] = time() + 30;

        // Assert
        $this->expectException(RateLimitExceededException::class);

        // Act
        $this->rateLimiter->hit('burst:test', '100/m', 20);
    }

    /**
     * Test that burst resets window when PTTL has expired (key without TTL).
     */
    public function testBurstResetsWhenPttlExpired(): void
    {
        // Arrange - Key exists but has no TTL (simulates missing EXPIRE)
        $cacheKey = 'burst:pttl:100/60';
        $this->mockRedis->storage[$cacheKey] = 5;
        // No $this->mockRedis->ttl set → pttl returns -1

        // Act
        $this->rateLimiter->hit('burst:pttl', '100/m', 20);

        // Assert - Should reset with setex
        $this->assertArrayHasKey('setex', $this->mockRedis->calls, 'Should reset with setex when PTTL expired');
        $setexCall = array_last($this->mockRedis->calls['setex']);
        $this->assertEquals($cacheKey, $setexCall['key'], 'Should reset the correct key');
        $this->assertEquals('1', $setexCall['value'], 'Should reset counter to 1');
    }

    /**
     * Test that burst catches up to ideal pace when used is behind.
     */
    public function testBurstCatchesUpToIdealPace(): void
    {
        // Arrange - 30 seconds remaining out of 60, used=10
        // ideal = floor((60 - 30000/1000) * 100 / 60) + 1 = floor(50) + 1 = 51
        // diff = 51 - 10 = 41
        $cacheKey = 'burst:catchup:100/60';
        $this->mockRedis->storage[$cacheKey] = 10;
        $this->mockRedis->ttl[$cacheKey] = time() + 30;

        // Act
        $this->rateLimiter->hit('burst:catchup', '100/m', 20);

        // Assert - Should call incrBy to catch up
        $this->assertArrayHasKey('incrBy', $this->mockRedis->calls, 'Should call incrBy to catch up');
        $incrByCall = $this->mockRedis->calls['incrBy'][0];
        $this->assertEquals($cacheKey, $incrByCall['key'], 'Should increment the correct key');
        $this->assertEquals(41, $incrByCall['increment'], 'Should catch up by ideal - used');
    }

    /**
     * Test that burst rejects when used exceeds ideal + burst allowance.
     */
    public function testBurstRejectsWhenExceedingBurstAllowance(): void
    {
        // Arrange - 30 seconds remaining, used=80
        // ideal = 51, burst = 20, 80 > 51 + 20 = 71 → reject
        $cacheKey = 'burst:exceed:100/60';
        $this->mockRedis->storage[$cacheKey] = 80;
        $this->mockRedis->ttl[$cacheKey] = time() + 30;

        // Assert
        $this->expectException(RateLimitExceededException::class);

        // Act
        $this->rateLimiter->hit('burst:exceed', '100/m', 20);
    }

    /**
     * Test that burst allows normal increment when within burst allowance.
     */
    public function testBurstAllowsNormalIncrementWithinAllowance(): void
    {
        // Arrange - 30 seconds remaining, used=60
        // ideal = 51, burst = 20, 60 <= 71 → allow, incr
        $cacheKey = 'burst:normal:100/60';
        $this->mockRedis->storage[$cacheKey] = 60;
        $this->mockRedis->ttl[$cacheKey] = time() + 30;

        // Act
        $this->rateLimiter->hit('burst:normal', '100/m', 20);

        // Assert - Should call incr (not incrBy, not setex for this branch)
        $incrCalls = array_filter(
            $this->mockRedis->calls['incr'] ?? [],
            fn ($call) => $call['key'] === $cacheKey
        );
        $this->assertCount(1, $incrCalls, 'Should call incr once for normal burst increment');
    }

    /**
     * Test that custom prefix is used in cache key.
     */
    public function testCustomPrefixInCacheKey(): void
    {
        // Arrange
        $method = new ReflectionMethod(self::class, 'dummyMethodWithRateLimit');
        $event = new RequestValidating($method);
        $rateLimiter = $this->makeRateLimiter(['prefix' => 'custom:rl:']);

        $this->mockIdentity->method('isGuest')->willReturn(true);
        $this->mockRequest->method('ip')->willReturn('1.2.3.4');

        // Act
        $rateLimiter->onValidating($event);

        // Assert
        $expectedKey = 'custom:rl:' . self::class . ':dummyMethodWithRateLimit:1.2.3.4:10/60';
        $this->assertEquals($expectedKey, $this->mockRedis->calls['incr'][0]['key'], 'Should use custom prefix');
    }

    /**
     * Test that limit without period uses default 60 seconds.
     */
    public function testLimitWithoutPeriodUsesDefault60Seconds(): void
    {
        // Act
        $this->rateLimiter->hit('noperiod:test', '10');

        // Assert - Key should use 10/60
        $this->assertEquals('noperiod:test:10/60', $this->mockRedis->calls['incr'][0]['key'], 'Should default to 60 second period');
    }

    /**
     * Test that hit() trims surrounding spaces in limit expression.
     */
    public function testHitTrimsWhitespaceInLimitExpression(): void
    {
        // Act
        $this->rateLimiter->hit('trim:test', ' 10/m ');

        // Assert
        $this->assertEquals('trim:test:10/60', $this->mockRedis->calls['incr'][0]['key']);
    }

    /**
     * Test that multiple limits with burst applies burst only to first rule.
     */
    public function testMultipleLimitsWithBurstOnlyAppliesToFirst(): void
    {
        // Act - burst=20, two limits
        $this->rateLimiter->hit('multi:burst', ['100/m', '1000/h'], 20);

        // Assert - First limit uses burst (setex), second uses fixed-window (incr)
        $this->assertArrayHasKey('setex', $this->mockRedis->calls, 'First limit should use burst (setex)');
        $this->assertEquals('multi:burst:100/60', $this->mockRedis->calls['setex'][0]['key'], 'Burst setex on first limit');

        $this->assertArrayHasKey('incr', $this->mockRedis->calls, 'Second limit should use fixed-window (incr)');
        $this->assertEquals('multi:burst:1000/3600', $this->mockRedis->calls['incr'][0]['key'], 'Fixed-window incr on second limit');
    }

    // Dummy methods for testing

    public function dummyMethodWithoutAttribute(): void
    {
    }

    #[RateLimit('10/m')]
    public function dummyMethodWithRateLimit(): void
    {
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeRateLimiter(array $overrides = []): RateLimiter
    {
        return $this->make(RateLimiter::class, array_merge([
            AppInterface::class => $this->mockApp,
            IdentityInterface::class => $this->mockIdentity,
            RequestInterface::class => $this->mockRequest,
            ClientInterface::class => $this->mockRedis,
            ListenerProviderInterface::class => $this->mockListenerProvider,
            EventDispatcherInterface::class => $this->mockEventDispatcher,
        ], $overrides));
    }
}

#[RateLimit('7/m')]
class ClassLevelRateLimitController
{
    public function indexAction(): void
    {
    }
}

#[RateLimit('9/m')]
class MethodOverridesClassRateLimitController
{
    #[RateLimit('2/m')]
    public function overrideAction(): void
    {
    }
}
