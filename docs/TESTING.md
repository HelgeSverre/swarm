# Testing Guide

## Overview

This project uses Pest PHP for testing, which provides a more expressive and developer-friendly testing experience compared to traditional PHPUnit.

## Running Tests

### Basic Test Commands

```bash
# Run all tests
composer test

# Run tests in a specific directory
./vendor/bin/pest tests/Unit

# Run tests matching a filter
./vendor/bin/pest --filter="webfetch"

# Run tests with verbose output
./vendor/bin/pest -v
```

### Code Coverage

#### Using Laravel Herd (Recommended)

If you're using Laravel Herd, it provides built-in support for code coverage with Xdebug:

```bash
# Run tests with coverage report
composer coverage

# Or directly with Herd
herd coverage ./vendor/bin/pest --coverage

# Generate HTML coverage report
herd coverage ./vendor/bin/pest --coverage --coverage-html=coverage/html

# Run with minimum coverage threshold
herd coverage ./vendor/bin/pest --coverage --min=80

# Generate text coverage report
herd coverage ./vendor/bin/pest --coverage --coverage-text=coverage/coverage.txt
```

#### Without Laravel Herd

If you're not using Herd, you need to install and configure Xdebug or PCOV:

```bash
# With Xdebug
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage

# With PCOV
./vendor/bin/pest --coverage
```

Coverage reports are generated in the `coverage/` directory.

## Installing Coverage Drivers

### Option 1: Xdebug (Recommended for Development)

```bash
# macOS with Homebrew
pecl install xdebug

# Or via package manager
brew install php@8.3-xdebug
```

### Option 2: PCOV (Faster, Production-Ready)

```bash
pecl install pcov
```

Enable in `php.ini`:
```ini
extension=pcov.so
pcov.enabled=1
```

## Testing Best Practices

### 1. HTTP Client Mocking

When testing tools that make HTTP requests, always mock the HTTP client:

```php
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

test('fetches data from API', function () {
    $mockResponse = new MockResponse('{"data": "test"}', [
        'http_code' => 200,
        'response_headers' => ['content-type' => 'application/json'],
    ]);
    
    $mockClient = new MockHttpClient([$mockResponse]);
    $tool = new WebFetch($mockClient);
    
    // Test your tool...
});
```

### 2. Exception Handling

For transport/network errors:
```php
use Symfony\Component\HttpClient\Exception\TransportException;

$mockClient = new MockHttpClient(function () {
    throw new TransportException('Connection timeout');
});
```

### 3. Test Organization

- **Unit Tests**: Test individual components in isolation
- **Feature Tests**: Test integration between components
- **Tool Tests**: Each tool should have comprehensive tests covering:
  - Schema generation
  - Successful execution
  - Error handling
  - Edge cases

### 4. Test Naming

Use descriptive test names that explain what is being tested:

```php
test('webfetch converts HTML to plain text')
test('webfetch handles network timeouts gracefully')
test('grep tool finds files matching pattern recursively')
```

### 5. Assertions

Pest provides expressive assertions:

```php
expect($result)->toBeTrue()
    ->and($data['status'])->toBe(200)
    ->and($data['content'])->toContain('expected text')
    ->and($data['content'])->not->toContain('<html>');
```

## Suggested Improvements

### 1. Test Fixtures

Create a `tests/Fixtures` directory for test data:
- Sample HTML files
- JSON responses
- CSV data
- Binary file samples

### 2. Test Helpers

Create custom test helpers in `tests/Helpers`:
```php
// tests/Helpers/HttpHelpers.php
function mockJsonResponse(array $data, int $status = 200): MockResponse
{
    return new MockResponse(json_encode($data), [
        'http_code' => $status,
        'response_headers' => ['content-type' => 'application/json'],
    ]);
}
```

### 3. Integration Tests

Add integration tests for the complete flow:
- User input → Agent classification → Task extraction → Tool execution → Response

### 4. Performance Testing

Consider adding performance benchmarks:
```php
test('processes large HTML files efficiently')
    ->time(500); // Must complete in 500ms
```

### 5. Mutation Testing

Add Infection PHP for mutation testing to ensure test quality:
```bash
composer require --dev infection/infection
```

### 6. Test Database

For tools that interact with databases:
- Use SQLite in-memory databases for speed
- Create database seeders for consistent test data
- Reset database state between tests

### 7. CI/CD Integration

Add GitHub Actions workflow for automated testing:
```yaml
# .github/workflows/tests.yml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          coverage: pcov
      - run: composer install
      - run: composer test:coverage:min
```

#### Coverage Badge

To add a coverage badge to your README:

1. Generate coverage in Clover format:
```bash
herd coverage ./vendor/bin/pest --coverage --coverage-clover=coverage/clover.xml
```

2. Use a service like Codecov or Coveralls:
   - Sign up for [Codecov](https://codecov.io) or [Coveralls](https://coveralls.io)
   - Add the repository
   - Upload coverage reports in your CI pipeline
   - Add the badge to your README:
   ```markdown
   [![Coverage Status](https://codecov.io/gh/username/repo/branch/main/graph/badge.svg)](https://codecov.io/gh/username/repo)
   ```

3. Or generate a local badge using the coverage percentage:
   - Parse the coverage percentage from the text report
   - Use shields.io to generate a dynamic badge
   - Example: `![Coverage](https://img.shields.io/badge/coverage-93%25-brightgreen)`

### 8. Test Documentation

For each new tool or feature:
1. Write tests first (TDD approach)
2. Document expected behavior in test descriptions
3. Include examples in tests that serve as documentation

### 9. Mock Service Container

Create a test service container for dependency injection:
```php
// tests/TestCase.php
protected function mockTool(string $toolClass, array $methods = []): Tool
{
    $mock = $this->createMock($toolClass);
    foreach ($methods as $method => $return) {
        $mock->method($method)->willReturn($return);
    }
    return $mock;
}
```

### 10. Snapshot Testing

For complex outputs, consider snapshot testing:
```php
test('generates expected OpenAI schema')
    ->expect(fn() => $tool->toOpenAISchema())
    ->toMatchSnapshot();
```

## Debugging Tests

### View Pest Configuration
```bash
./vendor/bin/pest --init
```

### Run Single Test
```bash
./vendor/bin/pest tests/Unit/Tools/WebFetchTest.php --filter="handles network errors"
```

### Debug with Ray
```php
test('debug example', function () {
    $data = ['test' => 'value'];
    ray($data)->green(); // Shows in Ray app
    
    expect($data)->toBeArray();
});
```

## Common Issues

### "No code coverage driver available"
Install Xdebug or PCOV as described above. If using Laravel Herd, Xdebug is already included.

### Xdebug Debug Client Warnings
If you see warnings like:
```
Xdebug: [Step Debug] Could not connect to debugging client. Tried: localhost:9003
```

This is harmless and means Xdebug is trying to connect to a debug client that isn't running. To disable these warnings:

1. In your `php.ini` or Herd's PHP configuration:
```ini
xdebug.mode=coverage
; Remove "debug" from the mode to disable step debugging
```

2. Or set it per command:
```bash
XDEBUG_MODE=coverage herd coverage ./vendor/bin/pest --coverage
```

### Tests timing out
Increase timeout in phpunit.xml or specific tests:
```php
test('long running test')
    ->timeout(10); // 10 seconds
```

### Memory issues
Increase memory limit for tests:
```bash
php -d memory_limit=512M vendor/bin/pest
```