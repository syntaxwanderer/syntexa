# AsRequestHandler Attribute

## Description

The `#[AsRequestHandler]` attribute marks a class as an HTTP Handler for a specific Request.  
Such handlers are automatically discovered by the framework and invoked to process the corresponding Request.

## Usage

```php
use Syntexa\Core\Attributes\AsRequestHandler;
use Syntexa\Core\Handler\HttpHandlerInterface;
use Syntexa\Core\Contract\RequestInterface;
use Syntexa\Core\Contract\ResponseInterface;

#[AsRequestHandler(
    doc: 'docs/attributes/AsRequestHandler.md',
    for: UserListRequest::class
)]
class UserListHandler implements HttpHandlerInterface
{
    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Handler logic
        return $response;
    }
}
```

## Parameters

### Required

- `doc` (string) - Path to the documentation file (relative to project root).
- `for` (string) - Request class that this handler processes.

### Optional

- `execution` (HandlerExecution|string|null) - Execution mode:
  - `'sync'` or `HandlerExecution::Sync` - synchronous execution (default).
  - `'async'` or `HandlerExecution::Async` - asynchronous execution via queue.
- `transport` (string|null) - Queue transport name (required for async):
  - `'memory'` - In-memory queue (for testing).
  - `'rabbitmq'` - RabbitMQ queue.
- `queue` (string|null) - Queue name (default: handler class name).
- `priority` (int|null) - Handler priority (higher = executed earlier, default: 0).

## Synchronous Handlers

Synchronous handlers are executed immediately during request processing:

```php
#[AsRequestHandler(
    doc: 'docs/attributes/AsRequestHandler.md',
    for: DashboardRequest::class,
    execution: 'sync'  // або HandlerExecution::Sync
)]
class DashboardHandler implements HttpHandlerInterface
{
    public function __construct(
        #[Inject] private UserRepository $userRepository
    ) {}

    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var DashboardRequest $request */
        $user = $this->userRepository->findCurrentUser();
        
        $response->setContext(['user' => $user]);
        return $response;
    }
}
```

## Asynchronous Handlers

Asynchronous handlers are executed via a queue:

```php
use Syntexa\Core\Queue\HandlerExecution;

#[AsRequestHandler(
    doc: 'docs/attributes/AsRequestHandler.md',
    for: EmailSendRequest::class,
    execution: HandlerExecution::Async,
    transport: 'rabbitmq',
    queue: 'emails',
    priority: 10
)]
class EmailSendHandler implements HttpHandlerInterface
{
    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // This code will run asynchronously in a worker process
        $this->emailService->send($request->email, $request->subject);
        return $response;
    }
}
```

## Handler Priorities

If there are multiple handlers for the same Request, they are executed in priority order:

```php
// Executed first (priority: 10)
#[AsRequestHandler(
    doc: 'docs/attributes/AsRequestHandler.md',
    for: UserRequest::class,
    priority: 10
)]
class UserValidationHandler implements HttpHandlerInterface {}

// Executed second (priority: 5)
#[AsRequestHandler(
    doc: 'docs/attributes/AsRequestHandler.md',
    for: UserRequest::class,
    priority: 5
)]
class UserLoggingHandler implements HttpHandlerInterface {}

// Executed last (priority: 0, default)
#[AsRequestHandler(
    doc: 'docs/attributes/AsRequestHandler.md',
    for: UserRequest::class
)]
class UserProcessingHandler implements HttpHandlerInterface {}
```

## Dependency Injection

Handlers support automatic dependency injection via PHP-DI:

```php
use DI\Attribute\Inject;

#[AsRequestHandler(
    doc: 'docs/attributes/AsRequestHandler.md',
    for: UserListRequest::class
)]
class UserListHandler implements HttpHandlerInterface
{
    public function __construct(
        #[Inject] private UserRepository $userRepository,
        #[Inject] private AuthService $authService,
        #[Inject] private LoggerInterface $logger
    ) {}

    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->logger->info('Processing user list request');
        $users = $this->userRepository->findAll();
        $response->setContext(['users' => $users]);
        return $response;
    }
}
```

## Requirements

1. Class MUST implement `HttpHandlerInterface`.
2. `handle()` MUST accept `RequestInterface` and `ResponseInterface` and return a `ResponseInterface`.
3. The `for` parameter MUST point to a class annotated with `#[AsRequest]`.
4. For async handlers the `transport` parameter is required.
5. The `doc` parameter is required and MUST point to an existing documentation file.

## Related attributes

- `#[AsRequest]` - Request DTO processed by the handler.
- `#[AsResponse]` - Response DTO returned by the handler.

## See also

- [AsRequest](AsRequest.md) - Creating Request DTOs.
- [AsResponse](AsResponse.md) - Creating Response DTOs.
- [Queue System](../../../packages/syntexa/core/src/Queue/README.md) - Queue system documentation.

