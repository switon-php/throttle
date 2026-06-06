<?php

declare(strict_types=1);

namespace Switon\Throttle;

use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionMethod;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Eventing\Attribute\EventListener;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Http\Event\RequestValidating;
use Switon\Http\RequestInterface;
use Switon\Principal\IdentityInterface;
use Switon\Redis\ClientInterface;
use Switon\Throttle\Attribute\RateLimit as RateLimitAttribute;
use Switon\Throttle\Event\Throttled;
use Switon\Throttle\Event\Throttling;
use Switon\Throttle\Exception\RateLimitExceededException;

use function sprintf;
use function strlen;
use function strpos;
use function substr;
use function trim;

/**
 * Redis-backed rate limiter implementation.
 *
 * Use when API endpoints need per-identity limits with optional burst tolerance and
 * attribute-based configuration via `#[RateLimit]`.
 *
 * @see \Switon\Throttle\RateLimiterInterface
 * @see \Switon\Throttle\Attribute\RateLimit
 * @see \Switon\Throttle\Exception\RateLimitExceededException
 * @see \Switon\Throttle\ServiceProvider
 * @see \Switon\Http\Event\RequestValidating
 * @see \Switon\Redis\ClientInterface
 */
class RateLimiter implements RateLimiterInterface
{
    #[Autowired] protected AppInterface $app;
    #[Autowired] protected IdentityInterface $identity;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ClientInterface $redisCache;
    #[Autowired] protected ListenerProviderInterface $listenerProvider;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;

    #[Autowired] protected ?string $prefix = null;
    /** @var list<string>|null Default limits for actions without #[RateLimit]; same normalized form as RateLimit attribute */
    #[Autowired] protected ?array $limits = null;
    #[Autowired] protected ?int $burst = null;
    #[Autowired] protected bool $enabled = true;

    /** @var array<string, RateLimitAttribute|false> Cache of controller::action => RateLimitAttribute or false (no limit) */
    protected array $rateLimits = [];

    public function boot(): void
    {
        if ($this->enabled) {
            $this->listenerProvider->register($this);
        }
    }

    protected function getRateLimit(ReflectionMethod $rMethod): RateLimitAttribute|false
    {
        if (($attributes = $rMethod->getAttributes(RateLimitAttribute::class)) !== []) {
            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return $attributes[0]->newInstance();
        }

        $rClass = $rMethod->getDeclaringClass();
        if (($attributes = $rClass->getAttributes(RateLimitAttribute::class)) !== []) {
            /** @noinspection PhpIncompatibleReturnTypeInspection */
            return $attributes[0]->newInstance();
        }

        return false;
    }

    #[EventListener] public function onValidating(RequestValidating $event): void
    {
        $controller = $event->controller;
        $action = $event->action;
        $key = "$controller::$action";
        if (($rateLimit = $this->rateLimits[$key] ?? null) === null) {
            $rateLimit = $this->rateLimits[$key] = $this->getRateLimit($event->method);
        }

        if ($rateLimit === false) {
            if ($this->limits !== null && $this->limits !== '' && $this->limits !== []) {
                $rateLimit = $this->rateLimits[$key] = new RateLimitAttribute($this->limits, $this->burst);
            } else {
                return;
            }
        }

        $uid = $this->identity->isGuest() ? $this->request->ip() : $this->identity->getName();
        $prefix = ($this->prefix ?? sprintf('cache:%s:rate_limit:', $this->app->id()))
            . $event->controller . ':' . $event->action . ':' . $uid;

        $this->hit($prefix, $rateLimit->limits, $rateLimit->burst);
    }

    /**
     * @param string|list<string> $limits
     */
    public function hit(string $key, string|array $limits, ?int $burst = null): void
    {
        if (is_string($limits)) {
            $limits = [$limits];
        }

        foreach ($limits as $k => $v) {
            $v = trim($v);
            if (($pos = strpos($v, '/')) !== false) {
                $limit = (int)substr($v, 0, $pos);
                $right = substr($v, $pos + 1);
                $period = RateLimitPeriodParser::parse(strlen($right) === 1 ? "1$right" : $right);
            } else {
                $limit = (int)$v;
                $period = 60;
            }

            $cacheKey = $key . ':' . $limit . '/' . $period;
            $burstForWindow = $k === 0 ? $burst : null;
            $this->eventDispatcher->dispatch(new Throttling($key, $limit, $period, $burstForWindow));

            if ($k === 0 && $burst !== null) {
                if (($used = (int)$this->redisCache->get($cacheKey)) === 0) {
                    $this->redisCache->setex($cacheKey, $period, '1');
                } elseif ($used >= $limit) {
                    $this->eventDispatcher->dispatch(new Throttled($key, $limit, $period, $used, $burstForWindow));
                    RateLimitExceededException::raise('Rate limit exceeded, please try again later');
                } elseif (($left = $this->redisCache->pttl($cacheKey)) <= 0) {
                    $this->redisCache->setex($cacheKey, $period, '1');
                } else {
                    $ideal = (int)(($period - $left / 1000) * $limit / $period) + 1;
                    if ($used < $ideal) {
                        $diff = $ideal - $used;
                        $this->redisCache->incrBy($cacheKey, $diff);
                    } elseif ($used > $ideal + $burst) {
                        $this->eventDispatcher->dispatch(new Throttled($key, $limit, $period, $used, $burstForWindow));
                        RateLimitExceededException::raise('Rate limit exceeded, please try again later');
                    } elseif ($this->redisCache->incr($cacheKey) === 1) {
                        $this->redisCache->expire($cacheKey, $period);
                    }
                }
            } elseif (($count = $this->redisCache->incr($cacheKey)) === 1) {
                $this->redisCache->expire($cacheKey, $period);
            } elseif ($count > $limit) {
                $this->eventDispatcher->dispatch(new Throttled($key, $limit, $period, $count, $burstForWindow));
                RateLimitExceededException::raise('Rate limit exceeded: {count}/{limit} requests', ['limit' => $limit, 'count' => $count]);
            }
        }
    }
}
