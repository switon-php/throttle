<?php

declare(strict_types=1);

namespace Switon\Throttle;

use Switon\Core\Exception\InvalidValueException;

/**
 * Parse rate-limit period expressions into seconds.
 *
 * Use when converting compact tokens such as `m`, `1m`, `2h`, or `0.5d` into window lengths.
 */
class RateLimitPeriodParser
{
    /**
     * Parses one rate-limit period token such as `m`, `1m`, or `2h`.
     */
    public static function parse(string $value): int
    {
        if (preg_match('#^([\d.]+)([smhdMy]?)$#', $value, $match) === 1) {
            $units = ['' => 1, 's' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400, 'M' => 2592000, 'y' => 31536000];

            return (int)((float)$match[1] * $units[$match[2]]);
        }

        InvalidValueException::raise('Invalid rate-limit period: "{value}".', ['value' => $value]);
    }
}
