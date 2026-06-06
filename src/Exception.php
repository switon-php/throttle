<?php

declare(strict_types=1);

namespace Switon\Throttle;

/**
 * Base exception for the Throttle component.
 *
 * Use when throttle-specific failures need one package-level exception root.
 *
 * @see \Switon\Throttle\RateLimiter
 */
class Exception extends \Switon\Core\Exception
{
}
