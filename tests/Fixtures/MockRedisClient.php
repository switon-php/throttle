<?php

declare(strict_types=1);

namespace Switon\Throttle\Tests\Fixtures;

use Switon\Redis\ClientInterface;

/**
 * Mock Redis client for throttle testing.
 *
 * Implements rate limiting specific Redis operations in memory.
 */
class MockRedisClient implements ClientInterface
{
    /**
     * In-memory storage for Redis operations.
     *
     * @var array<string, mixed> Key-value pairs
     */
    public array $storage = [];

    /**
     * TTL storage for keys (expiration timestamps).
     *
     * @var array<string, int> Key to expiration timestamp mapping
     */
    public array $ttl = [];

    /**
     * Track method calls for assertions.
     *
     * @var array<string, array> Method call history
     */
    public array $calls = [];

    public function getUri(): ?string
    {
        return null;
    }

    public function getTransient(): static
    {
        return $this;
    }

    /**
     * Get value from storage.
     */
    public function get(string $key): string|false
    {
        $this->calls['get'][] = ['key' => $key];

        if (!isset($this->storage[$key])) {
            return false;
        }

        // Check TTL
        if (isset($this->ttl[$key]) && $this->ttl[$key] < time()) {
            unset($this->storage[$key], $this->ttl[$key]);
            return false;
        }

        return (string)$this->storage[$key];
    }

    /**
     * Set value with expiration (seconds).
     */
    public function setex(string $key, int $ttl, mixed $value): bool
    {
        $this->calls['setex'][] = ['key' => $key, 'ttl' => $ttl, 'value' => $value];

        $this->storage[$key] = $value;
        $this->ttl[$key] = time() + $ttl;

        return true;
    }

    /**
     * Increment value by 1.
     */
    public function incr(string $key): int
    {
        $this->calls['incr'][] = ['key' => $key];

        if (!isset($this->storage[$key])) {
            $this->storage[$key] = 1;
            return 1;
        }

        $this->storage[$key] = (int)$this->storage[$key] + 1;
        return (int)$this->storage[$key];
    }

    /**
     * Increment value by specific amount.
     */
    public function incrBy(string $key, int $increment): int
    {
        $this->calls['incrBy'][] = ['key' => $key, 'increment' => $increment];

        if (!isset($this->storage[$key])) {
            $this->storage[$key] = $increment;
            return $increment;
        }

        $this->storage[$key] = (int)$this->storage[$key] + $increment;
        return (int)$this->storage[$key];
    }

    /**
     * Set expiration time (seconds).
     */
    public function expire(string $key, int $ttl): bool
    {
        $this->calls['expire'][] = ['key' => $key, 'ttl' => $ttl];

        if (!isset($this->storage[$key])) {
            return false;
        }

        $this->ttl[$key] = time() + $ttl;
        return true;
    }

    /**
     * Get time to live in milliseconds.
     */
    public function pttl(string $key): int
    {
        $this->calls['pttl'][] = ['key' => $key];

        if (!isset($this->storage[$key])) {
            return -2; // Key does not exist
        }

        if (!isset($this->ttl[$key])) {
            return -1; // Key exists but has no TTL
        }

        $remaining = $this->ttl[$key] - time();
        if ($remaining <= 0) {
            unset($this->storage[$key], $this->ttl[$key]);
            return -2;
        }

        return $remaining * 1000; // Convert to milliseconds
    }

    /**
     * Delete key from storage.
     */
    public function del(string|array $key): int
    {
        $keys = is_array($key) ? $key : [$key];
        $this->calls['del'][] = ['keys' => $keys];

        $deleted = 0;
        foreach ($keys as $k) {
            if (isset($this->storage[$k])) {
                unset($this->storage[$k]);
                unset($this->ttl[$k]);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Clear all storage (for test cleanup).
     */
    public function clear(): void
    {
        $this->storage = [];
        $this->ttl = [];
        $this->calls = [];
    }
}
