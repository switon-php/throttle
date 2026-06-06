<?php

declare(strict_types=1);

namespace Switon\Throttle\Tests\Unit;

use Switon\Core\Exception\InvalidValueException;
use Switon\Throttle\RateLimitPeriodParser;
use Switon\Throttle\Tests\TestCase;

class RateLimitPeriodParserTest extends TestCase
{
    public function testParseShortUnitAlias(): void
    {
        $this->assertSame(60, RateLimitPeriodParser::parse('1m'));
        $this->assertSame(3600, RateLimitPeriodParser::parse('1h'));
    }

    public function testParseExplicitValue(): void
    {
        $this->assertSame(120, RateLimitPeriodParser::parse('2m'));
        $this->assertSame(86400, RateLimitPeriodParser::parse('1d'));
        $this->assertSame(2592000, RateLimitPeriodParser::parse('1M'));
    }

    public function testParseRejectsInvalidExpression(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('Invalid rate-limit period');

        RateLimitPeriodParser::parse('tomorrow');
    }

    public function testParseSupportsDecimalAndTruncatesToIntSeconds(): void
    {
        $this->assertSame(90, RateLimitPeriodParser::parse('1.5m'));
    }
}
