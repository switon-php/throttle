# Throttle FAQ {#throttle-faq}

Short answers for the most common `throttle` questions.

> **Prerequisites:** [Throttle Package](README.md)

## Getting Started {#getting-started}

### How do I add rate limiting to an action? {#add-rate-limiting}

Add `#[RateLimit('10/m')]` to the class or action.

### How do I rate limit non-HTTP work? {#non-http}

Inject `RateLimiterInterface` and call `hit()`.

### What happens when a limit is exceeded? {#limit-exceeded}

`Switon\Throttle\Exception\RateLimitExceededException` is raised. In HTTP flows it renders as status `429`.

## Configuration {#configuration}

### How do I configure defaults? {#configure-defaults}

Set `Switon\Throttle\RateLimiterInterface` in `switon.yml`.

### Can I disable automatic request throttling? {#disable-automatic}

Yes. Set `enabled: false`. Direct `hit()` calls still work.

### How do I inspect the current options? {#discover-options}

Run `bash bin/console config:options 'Switon\Throttle\RateLimiterInterface'`.

## Limits {#limits}

### What rule formats are supported? {#rule-formats}

Use `limit/period` or a bare `limit`. Supported units are `s`, `m`, `h`, `d`, `M`, and `y`.
Single-letter periods such as `m` are treated as `1m` first, and decimal values are truncated to whole seconds after
parsing.

### Can I combine multiple limits? {#multiple-limits}

Yes. Pass an array such as `['10/m', '100/h']`; every rule must pass.

### Does `burst` apply to every rule? {#burst}

No. It applies only to the first rule in the list.
The first-window burst path follows an internal pace calculation, not a simple pre-add of extra requests.

### Does config `prefix` apply to `hit()` keys? {#prefix-hit}

No. Manual keys are used as-is.

## Troubleshooting {#troubleshooting}

### Throttling is not working. What should I check first? {#not-working}

Check Redis wiring, whether the request has `#[RateLimit]` or fallback `limits`, and whether `enabled` is still `true`.

### Why am I getting `429` sooner than expected? {#unexpected-429}

Check whether multiple rules are active, whether several requests share the same identity, whether all instances use the
same Redis backend, and whether the current rule uses burst pacing.

### What do the throttle events carry? {#events}

`Throttling` is DEBUG and carries `key`, `limit`, `period`, and `burst`; `Throttled` is INFO and carries `key`, `limit`,
`period`, `count`, and `burst`.

### How do I add `Retry-After` or `X-RateLimit-*` headers? {#headers}

Add them in your exception or response layer. The package does not emit them automatically.
