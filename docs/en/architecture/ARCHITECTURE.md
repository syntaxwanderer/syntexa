# Syntexa Architecture: Swoole Only

## 🎯 Core Philosophy

Syntexa runs exclusively on the Swoole HTTP server.

## 📊 Current Implementation

### 1. **Unified Request Handling**

```php
// Swoole mode  
$request = Request::create($swooleRequest);  // auto: Swoole request
```

The `RequestFactory` expects a Swoole request and creates the appropriate `Request` object.

### 2. **Application Logic is Runtime-Agnostic (within Swoole)**

```php
// server.php (Swoole)
$app = new Application();
$response = $app->handleRequest($request);
```

**The same `Application` class handles all HTTP requests under Swoole.**

### 3. **Immutable Architecture**

All core classes use `readonly`:

```php
readonly class Environment { ... }
readonly class Request { ... }
readonly class Response { ... }
```

This eliminates:
- ❌ Global state issues
- ❌ Memory leaks in long-running processes
- ❌ Race conditions

## 🚀 Swoole Features Available

When running in Swoole mode, you can leverage:

### 1. **Async I/O Operations**

```php
use Swoole\Coroutine\Http\Client;

class AsyncDataAction
{
    public function __invoke(): Response
    {
        // Multiple API calls in parallel
        $results = [];
        
        foreach ($urls as $url) {
            $client = new Client($host, $port);
            $client->get($path);
            $results[] = $client->body;
        }
        
        return Response::json($results);
    }
}
```

### 2. **Database Connection Pooling**

```php
use Swoole\Database\PDOPool;

class DatabasePool
{
    private static ?PDOPool $pool = null;
    
    public static function getPool(): PDOPool
    {
        if (!self::$pool) {
            self::$pool = new PDOPool(
                size: 10,
                config: [...]
            );
        }
        return self::$pool;
    }
}
```

### 3. **WebSocket Support**

```php
$server->on('open', function($server, $request) {
    echo "Connection opened: {$request->fd}\n";
});

$server->on('message', function($server, $frame) {
    $server->push($frame->fd, "Server: {$frame->data}");
});

$server->on('close', function($server, $fd) {
    echo "Connection closed: {$fd}\n";
});
```

### 4. **Long-Running Tasks**

```php
use Swoole\Coroutine;

Coroutine::create(function () {
    // Long-running task doesn't block
    while (true) {
        processQueue();
        Coroutine::sleep(1);
    }
});
```

## 📊 Performance Characteristics

### Swoole Mode
- ✅ 10-100x faster for I/O operations
- ✅ Support for async operations
- ✅ Built-in connection pooling
- ✅ WebSocket support out of the box
- ⚠️ Harder to debug (no Xdebug)
- ⚠️ Requires Swoole extension

## 🔧 How It Works

### Entry Point

**Swoole** (`server.php`):
```php
require_once __DIR__ . '/vendor/autoload.php';
$app = new Application();

$server->on("request", function ($request, $response) use ($app) {
    $syntexaRequest = Request::create($request);  // Uses Swoole request
    $syntexaResponse = $app->handleRequest($syntexaRequest);
    
    // Send response back to Swoole
    $response->status($syntexaResponse->getStatusCode());
    foreach ($syntexaResponse->getHeaders() as $name => $value) {
        $response->header($name, $value);
    }
    $response->end($syntexaResponse->getContent());
});
```

### RequestFactory

```php
class RequestFactory
{
    public static function create(mixed $source = null): Request
    {
        if ($source instanceof \Swoole\Http\Request) {
            return self::fromSwoole($source);
        }
        throw new \InvalidArgumentException('Swoole request required');
    }
}
```

The factory requires a Swoole request.

## 🎯 Best Practices

### 1. **Business Logic**

Write business logic **mode-agnostic**:

```php
#[AsHttpRequest(path: '/api/users', methods: ['GET'])]
class UserListRequest implements RequestInterface
{
    // Request DTO
}

#[AsHttpHandler(for: UserListRequest::class)]
class UserListHandler implements HttpHandlerInterface
{
    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // This code runs under Swoole
        $users = $this->repository->findAll();
        $response->setRenderContext(['users' => $users]);
        return $response;
    }
}
```

### 2. **Async Operations (Swoole Only)**

Use async features **conditionally**:

```php
#[AsHttpRequest(path: '/api/data', methods: ['GET'])]
class AsyncDataRequest implements RequestInterface
{
    // Request DTO
}

#[AsHttpHandler(for: AsyncDataRequest::class)]
class AsyncDataHandler implements HttpHandlerInterface
{
    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (extension_loaded('swoole')) {
            // Use coroutines for parallel requests
            return $this->asyncFetch();
        }
        
        // Synchronous fallback if async not desired
        return $this->syncFetch();
    }
}
```

### 3. **Database Access**

Use connection pooling **only in Swoole**:

```php
class DatabaseService
{
    public function query(string $sql): array
    {
        if (extension_loaded('swoole')) {
            // Use connection pool
            $pool = DatabasePool::getPool();
            $conn = $pool->get();
            // ... query
            return $results;
        }
        
        // Regular PDO (non-pooled)
        $pdo = new PDO(...);
        return $pdo->query($sql)->fetchAll();
    }
}
```

## 📈 Future Enhancements

### Planned Features

1. **Fiber Support (PHP 8.2)**
   - Native async/await syntax
   - Works in both runtimes

2. **GraphQL Support**
   - Async subscriptions via WebSocket
   - Regular queries in PHP built-in

3. **Event Sourcing**
   - Events work in both modes
   - Real-time listeners in Swoole

4. **CQRS**
   - Same commands/queries in both modes
   - Async handlers available in Swoole

## 🧩 Overlay Architecture

Syntexa uses a unique **Overlay Layer** system. Files in `/src` have higher priority than those in `packages/`. This allows you to override standard module behavior (Requests, Handlers, Entities) without modifying the framework core.

See [OVERLAY_ARCHITECTURE.md](./OVERLAY_ARCHITECTURE.md) for a detailed explanation of the prioritization system.

## 🎓 Summary

The architecture is:
- ✅ Simple - unified Request/Response
- ✅ Performant - Swoole features by default

