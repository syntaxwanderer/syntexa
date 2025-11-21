# Syntexa DI Container

Syntexa uses PHP-DI for dependency injection, configured for Swoole long-running processes.

## Key Features

- **Swoole-safe**: Container is reset after each request to prevent memory leaks
- **Autowiring**: Automatic dependency resolution
- **Module support**: Modules can register services via `ServiceProviderInterface`

## Usage

### Basic Usage

```php
use Syntexa\Core\Container\ContainerFactory;

// Get container
$container = ContainerFactory::get();

// Get service
$service = $container->get(SomeService::class);

// Container is automatically reset after each Swoole request
```

### In Handlers

```php
use DI\Attribute\Inject;

class MyHandler implements HttpHandlerInterface
{
    public function __construct(
        #[Inject] private MyService $myService
    ) {}
    
    public function handle(RequestInterface $request, ResponseInterface $response)
    {
        // Use injected service
        $this->myService->doSomething();
        return $response;
    }
}
```

### Registering Services

Services are registered in `ContainerFactory::getDefinitions()`. Modules can extend this via service providers.

## Swoole Integration

Syntexa uses a **RequestScopedContainer** wrapper that ensures request-scoped services (handlers, services) get fresh instances for each request, while infrastructure services (registries, pools) remain singletons.

### How It Works:

1. **RequestScopedContainer** automatically detects which services should be request-scoped:
   - Handlers, Services, Repositories → request-scoped (new instance each request)
   - Environment, Registry, Factory, Pool → singleton (safe to persist)

2. After each request, the request-scoped cache is reset in `server.php`

3. Handlers are resolved through `RequestScopedContainer`, ensuring clean state

### Best Practices for Swoole:

1. **Request-scoped services** (handlers, services that handle request data):
   - Automatically handled by `RequestScopedContainer`
   - Use `$app->getRequestScopedContainer()->get(Service::class)`

2. **Infrastructure services** (registries, connection pools):
   - Use singleton pattern - safe to persist
   - Use `$app->getContainer()->get(Registry::class)`

3. **Never store request data in singletons** - always use request-scoped resolution

This ensures:
- ✅ No memory leaks between requests
- ✅ No data leakage between requests  
- ✅ Clean state for each request
- ✅ Optimal performance (singletons for infrastructure)

