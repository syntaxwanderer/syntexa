# Syntexa Framework Tests

This directory contains tests for the Syntexa framework, using PHPUnit.

## Structure

- `WebTestCase.php` - Base test case for HTTP/web tests (similar to Symfony's WebTestCase)
- `TestClient.php` - Test client for making HTTP requests without a real Swoole server
- `Controller/` - Tests for HTTP handlers/controllers

## Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/Controller/LoginFormHandlerTest.php

# Run with testdox output (more readable)
vendor/bin/phpunit --testdox

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

## Writing Tests

### Example: Testing an HTTP Handler

```php
<?php

namespace Syntexa\Tests\Controller;

use Syntexa\Tests\WebTestCase;

class MyHandlerTest extends WebTestCase
{
    public function testSomething(): void
    {
        $client = $this->createClient();
        $response = $client->get('/my-route');

        $this->assertResponseIsSuccessful($response);
        $this->assertResponseContains($response, 'Expected text');
    }
}
```

### Available Assertions

- `assertResponseIsSuccessful(Response $response)` - Checks status code 200-299
- `assertResponseStatusCode(Response $response, int $code)` - Checks exact status code
- `assertResponseContains(Response $response, string $text)` - Checks if response contains text
- `assertResponseHeader(Response $response, string $header, string $value)` - Checks response header
- `assertResponseRedirects(Response $response, ?string $location)` - Checks redirect response
- `assertResponseJson(Response $response, ?array $data)` - Checks JSON response

### Test Client Methods

- `get(string $uri, array $parameters = [], ...)` - Make GET request
- `post(string $uri, array $parameters = [], ...)` - Make POST request
- `put(string $uri, array $parameters = [], ...)` - Make PUT request
- `delete(string $uri, array $parameters = [], ...)` - Make DELETE request
- `request(string $method, string $uri, ...)` - Make custom request

## How It Works

Unlike Symfony's WebTestCase which requires a real web server, Syntexa's test client works directly with `Application::handleRequest()`. This means:

1. **No Swoole server needed** - Tests run without starting a real server
2. **Faster execution** - Direct method calls instead of HTTP requests
3. **Easier debugging** - Can use xdebug and step through code normally
4. **Same behavior** - Uses the same Application and routing logic as production

The `TestClient` creates `Request` objects and passes them directly to `Application::handleRequest()`, returning the `Response` object for assertions.

