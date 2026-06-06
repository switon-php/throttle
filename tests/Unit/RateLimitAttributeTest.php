<?php

declare(strict_types=1);

namespace Switon\Throttle\Tests\Unit;

use Switon\Throttle\Attribute\RateLimit;
use Switon\Throttle\Tests\TestCase;

class RateLimitAttributeTest extends TestCase
{
    public function testConstructWithStringNormalizesToSingleItemArray(): void
    {
        $attribute = new RateLimit('10/m');

        $this->assertSame(['10/m'], $attribute->limits);
        $this->assertNull($attribute->burst);
    }

    public function testConstructWithArrayKeepsAllLimitsAndBurst(): void
    {
        $attribute = new RateLimit(['10/m', '100/h'], 20);

        $this->assertSame(['10/m', '100/h'], $attribute->limits);
        $this->assertSame(20, $attribute->burst);
    }
}
