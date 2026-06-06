# Switon Throttle Package Tests

Test suite for the Throttle package.

## Running Tests

```bash
vendor/bin/phpunit --configuration tests/phpunit.xml.dist
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --testsuite=unit
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --testsuite=integration
```

Install dev dependencies first so `vendor/bin/phpunit` is available.

## Test Structure

```
tests/
├── Unit/                    # Unit tests (no external dependencies)
│   └── RateLimiterTest.php
├── Integration/             # Integration tests (requires Redis)
├── TestCase.php             # Base test case
└── phpunit.xml.dist         # PHPUnit configuration
```

## Writing Tests

### Unit Tests

Unit tests should mock all dependencies and test logic in isolation:

```php
class SomeTest extends TestCase
{
    public function testSomething(): void
    {
        // Arrange
        $mock = $this->createMock(SomeInterface::class);
        
        // Act
        $result = $someMethod();
        
        // Assert
        $this->assertSame('expected', $result);
    }
}
```

### Integration Tests

Integration tests may use real services (Redis, etc.) and test end-to-end behavior.

## Coverage

Run tests with coverage:

```bash
vendor/bin/phpunit --configuration tests/phpunit.xml.dist --coverage-html tests/coverage
```

View coverage report at `tests/coverage/index.html`.
