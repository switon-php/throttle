# Design Rationale {#design-rationale}

`switon/throttle` keeps request protection small: shared counters, explicit keys, and one clear path for HTTP and manual
checks.

## See also {#see-also}

- [Throttle Package](README.md) — usage, configuration, and limit syntax
- [FAQ](faq.md#throttle-faq) — short answers for setup and behavior

---

## Core Problem {#core-problem}

Rate limiting gets messy when each route invents its own counters or policy pattern.

- counters drift across workers
- limits move away from the request boundary
- burst behavior gets duplicated in ad hoc code
- business quotas and basic abuse protection get mixed together

The package standardizes the common boundary first.

## Why Redis {#why-redis}

`switon/throttle` uses Redis so counters stay shared across workers and instances.

That choice favors consistent enforcement over a local in-memory fallback that would answer differently on each process.

## Why Attributes First {#why-attributes-first}

`#[RateLimit]` keeps the normal HTTP path close to the code it protects.

- class attributes define a baseline
- method attributes override the class
- manual `hit()` stays available when the key belongs to application code

This makes the default path easy to review without hiding the option for custom keys.

## Burst Is Deliberately Narrow {#burst-narrow}

Burst tolerance only applies to the first rule in a list.

That keeps the main window responsive to short spikes without turning every rule into a more complex algorithm.

## Deliberate Simplicity {#deliberate-simplicity}

Throttle is a limiter, not a quota engine.

- no per-destination policy tree
- no built-in whitelist model
- no automatic rate-limit headers
- no product-tier logic

The trade-off is less policy surface. The gain is a small, predictable limiter that stays close to the request boundary.
