# Switon Throttle Package

[![CI](https://img.shields.io/github/actions/workflow/status/switon-php/throttle/ci.yml?branch=main&label=CI)](https://github.com/switon-php/throttle/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's request throttling layer for `#[RateLimit]` rules, burst-aware windows, and HTTP 429 rejections.

## Highlights

- **Declarative limits:** `#[RateLimit]` can be applied to controllers or actions.
- **Layered rules:** class defaults set the baseline, and method rules can override them.
- **Programmatic checks:** the same limit syntax can be used directly.
- **Burst handling:** the first window can allow burst tolerance.
- **Rejection visibility:** `Throttling` and `Throttled` surface the applied window and outcome.

## Installation

```bash
composer require switon/throttle
```

## Quick Start

```php
use Switon\Throttle\Attribute\RateLimit;

#[RateLimit('10/m')]
final class ApiController
{
    public function searchAction(): array
    {
        return [];
    }
}
```

Docs: https://docs.switon.dev/latest/throttle

## License

MIT.
