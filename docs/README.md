# Throttle Package {#throttle-package}

`switon/throttle` provides Redis-backed rate limiting for HTTP actions and direct application checks.

**Key features:**

- **`#[RateLimit]` on controllers or actions** — keep limits close to the endpoint they protect
- **Shared Redis counters** — limits stay consistent across workers and instances
- **Direct `hit()` checks** — use the same limiter in queues, CLI jobs, or service code
- **Multiple windows** — require every configured window to pass
- **Optional burst on the first window** — allow short spikes without changing every rule

---

## Installation {#installation}

**Requirements:** PHP 8.3+ plus the framework pieces that provide HTTP, identity, events, and Redis (`switon/http`,
`switon/principal`, `switon/event`, `switon/redis`).

```bash
composer require switon/throttle
```

---

## Quick Start {#quick-start}

Add `#[RateLimit]` to the controller or action you want to protect.

```php
use Switon\Throttle\Attribute\RateLimit;

#[RateLimit('10/m')]
final class ApiController
{
    public function search(): array
    {
        return [];
    }
}
```

When the limit is exceeded, `Switon\Throttle\Exception\RateLimitExceededException` is raised and surfaces as HTTP `429`
in normal request flows.

---

## Core Concepts {#core-concepts}

### Limits {#limits}

Use `limit/period` or a bare `limit`.

- `10/m` means 10 requests per minute
- `100/h` means 100 requests per hour
- `10` means 10 requests per 60 seconds

You can pass one rule or several. Every rule must pass.

### Attribute Scope {#attribute-scope}

`#[RateLimit]` can be placed on a class or a method.

- method-level rules override class-level rules
- if no attribute is present, config fallback limits can apply
- burst is read from the same attribute or fallback config

### Manual Checks {#manual-checks}

Use `RateLimiterInterface::hit()` when throttling belongs to your own code path.

```php
use Switon\Core\Attribute\Autowired;
use Switon\Throttle\RateLimiterInterface;

final class SmsService
{
    #[Autowired] protected RateLimiterInterface $rateLimiter;

    public function send(string $phone): void
    {
        $this->rateLimiter->hit('sms:' . $phone, '5/m');
    }
}
```

### Identity {#identity}

For automatic HTTP throttling, the limiter keys guest and authenticated requests differently.

- guest requests use the client IP
- authenticated requests use `IdentityInterface::getName()`

### Keys {#keys}

The limiter key also includes controller, action, and rule window.

- custom `prefix` is prepended verbatim to attribute-driven keys
- each configured rule appends its own `:{limit}/{period}` suffix

### Burst {#burst}

`burst` applies only to the first rule in a rule list.

That keeps the normal path simple while still allowing short spikes on the most immediate window.
The burst path tracks pace against an internal reference counter rather than just pre-adding a fixed number of requests.

---

## Configuration {#configuration}

Throttle can run from attributes alone. Add config only when you want defaults, a custom prefix, or temporary
disablement.

```yaml
# switon.yml
Switon\Throttle\RateLimiterInterface:
  enabled: true
  prefix: cache:my-app:rate_limit:
  limits: ['60/m']
  burst: 20
```

Common options:

- `enabled` turns the automatic request-validation listener on or off
- `prefix` sets the Redis key prefix for attribute-driven limits
- `limits` sets fallback rules when no `#[RateLimit]` is present
- `burst` sets the fallback burst for the first rule

Automatic throttling hooks into `RequestValidating`, and `ServiceProvider::boot()` only calls
`RateLimiterInterface::boot()` after the limiter has been enabled.

### Option Discovery {#option-discovery}

```bash
bash bin/console config:options 'Switon\Throttle\RateLimiterInterface'
```

Throttle also depends on `Switon\Redis\ClientInterface`.

---

## Keep in Mind {#keep-in-mind}

- `enabled: false` only disables automatic request validation.
- `prefix` does not apply to manual `hit()` keys.
- `burst` only affects the first configured rule.
- This package does not add response headers by itself.
- This package is not a quota policy engine.
- `#[RateLimit]` is read once per method/class; multiple attributes are not merged.

---

## Next {#next}

- **Need setup questions answered quickly:** [FAQ](faq.md#throttle-faq)
- **Need design trade-offs:** [Design Rationale](design.md#design-rationale)
- **HTTP actions and request flow:** [HTTP Package](https://github.com/switon-php/http/blob/main/docs/README.md)
- **Redis-backed counters:** [Redis Package](https://github.com/switon-php/redis/blob/main/docs/README.md)
