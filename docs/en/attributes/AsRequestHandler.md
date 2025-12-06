# AsRequestHandler Attribute

## Опис

Атрибут `#[AsRequestHandler]` позначає клас як HTTP Handler для обробки конкретного Request. Handler автоматично виявляється фреймворком та викликається при обробці відповідного Request.

## Використання

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

## Параметри

### Обов'язкові

- `doc` (string) - Шлях до файлу документації (відносно кореня проекту)
- `for` (string) - Клас Request, який обробляє цей handler

### Опціональні

- `execution` (HandlerExecution|string|null) - Режим виконання:
  - `'sync'` або `HandlerExecution::Sync` - синхронне виконання (за замовчуванням)
  - `'async'` або `HandlerExecution::Async` - асинхронне виконання через чергу
- `transport` (string|null) - Назва транспорту черги (обов'язковий для async):
  - `'memory'` - In-memory черга (для тестування)
  - `'rabbitmq'` - RabbitMQ черга
- `queue` (string|null) - Назва черги (за замовчуванням: назва класу handler)
- `priority` (int|null) - Пріоритет handler (вищий = виконується першим, за замовчуванням: 0)

## Синхронні Handlers

Синхронні handlers виконуються одразу під час обробки запиту:

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

## Асинхронні Handlers

Асинхронні handlers виконуються через чергу:

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
        // Цей код виконається асинхронно в worker процесі
        $this->emailService->send($request->email, $request->subject);
        return $response;
    }
}
```

## Пріоритети Handlers

Якщо для одного Request є кілька handlers, вони виконуються в порядку пріоритету:

```php
// Виконається першим (priority: 10)
#[AsRequestHandler(
    doc: 'docs/attributes/AsRequestHandler.md',
    for: UserRequest::class,
    priority: 10
)]
class UserValidationHandler implements HttpHandlerInterface {}

// Виконається другим (priority: 5)
#[AsRequestHandler(
    doc: 'docs/attributes/AsRequestHandler.md',
    for: UserRequest::class,
    priority: 5
)]
class UserLoggingHandler implements HttpHandlerInterface {}

// Виконається останнім (priority: 0, за замовчуванням)
#[AsRequestHandler(
    doc: 'docs/attributes/AsRequestHandler.md',
    for: UserRequest::class
)]
class UserProcessingHandler implements HttpHandlerInterface {}
```

## Dependency Injection

Handlers підтримують автоматичну ін'єкцію залежностей через PHP-DI:

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

## Вимоги

1. Клас повинен реалізувати `HttpHandlerInterface`
2. Метод `handle()` повинен приймати `RequestInterface` та `ResponseInterface` і повертати `ResponseInterface`
3. Параметр `for` повинен вказувати на клас з атрибутом `#[AsRequest]`
4. Для async handlers обов'язковий параметр `transport`
5. Параметр `doc` обов'язковий та повинен вказувати на існуючий файл документації

## Пов'язані атрибути

- `#[AsRequest]` - Request DTO, який обробляє handler
- `#[AsResponse]` - Response DTO, який повертає handler

## Див. також

- [AsRequest](AsRequest.md) - Створення Request DTO
- [AsResponse](AsResponse.md) - Створення Response DTO
- [Queue System](../../../packages/syntexa/core/src/Queue/README.md) - Документація системи черг

