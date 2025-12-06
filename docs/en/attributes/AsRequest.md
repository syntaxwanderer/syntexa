# AsRequest Attribute

## Опис

Атрибут `#[AsRequest]` позначає клас як HTTP Request DTO (Data Transfer Object) та визначає маршрут, методи та інші параметри роутингу.

## Використання

```php
use Syntexa\Core\Attributes\AsRequest;
use Syntexa\Core\Contract\RequestInterface;

#[AsRequest(
    doc: 'docs/attributes/AsRequest.md',
    path: '/api/users',
    methods: ['GET'],
    name: 'api.users.list'
)]
class UserListRequest implements RequestInterface
{
    // Request properties
}
```

## Параметри

### Обов'язкові

- `doc` (string) - Шлях до файлу документації (відносно кореня проекту)

### Опціональні

- `base` (string|null) - Базовий Request клас для наслідування
- `responseWith` (string|null) - Клас Response, який буде використано
- `path` (string|null) - URL шлях маршруту (обов'язковий якщо немає `base`)
- `methods` (array|null) - HTTP методи (за замовчуванням: `['GET']`)
- `name` (string|null) - Ім'я маршруту (за замовчуванням: коротка назва класу)
- `requirements` (array|null) - Вимоги до параметрів маршруту
- `defaults` (array|null) - Значення за замовчуванням для параметрів
- `options` (array|null) - Додаткові опції маршруту
- `tags` (array|null) - Теги для маршруту
- `public` (bool|null) - Чи публічний маршрут (за замовчуванням: `true`)

## Environment Variables

Ви можете використовувати змінні оточення в будь-якому значенні атрибута:

- `env::VAR_NAME` - читає з .env файлу, повертає порожній рядок якщо не встановлено
- `env::VAR_NAME::default_value` - читає з .env файлу, повертає default якщо не встановлено (рекомендовано)
- `env::VAR_NAME:default_value` - старий формат, також підтримується

**Рекомендовано використовувати подвійний двокрапка (`::`)**, оскільки він дозволяє використовувати двокрапки в значеннях за замовчуванням.

### Приклад з environment variables:

```php
#[AsRequest(
    doc: 'docs/attributes/AsRequest.md',
    path: 'env::API_LOGIN_PATH::/api/login',
    methods: ['POST'],
    name: 'env::API_LOGIN_ROUTE_NAME::api.login',
    responseWith: 'env::API_LOGIN_RESPONSE_CLASS::LoginApiResponse'
)]
class LoginRequest implements RequestInterface
{
    public string $email;
    public string $password;
}
```

## Наслідування через base

Ви можете наслідувати параметри від іншого Request:

```php
// Base request
#[AsRequest(
    doc: 'docs/attributes/AsRequest.md',
    path: '/api',
    methods: ['GET']
)]
class BaseApiRequest implements RequestInterface {}

// Derived request
#[AsRequest(
    doc: 'docs/attributes/AsRequest.md',
    base: BaseApiRequest::class,
    path: '/users'  // Перевизначає path, але наслідує methods
)]
class UserListRequest extends BaseApiRequest {}
```

## Пов'язані атрибути

- `#[AsRequestHandler]` - Handler для обробки Request
- `#[AsRequestPart]` - Trait для розширення Request
- `#[AsResponse]` - Response DTO

## Приклади

### Базовий GET запит

```php
#[AsRequest(
    doc: 'docs/attributes/AsRequest.md',
    path: '/dashboard',
    methods: ['GET']
)]
class DashboardRequest implements RequestInterface {}
```

### POST запит з валідацією

```php
#[AsRequest(
    doc: 'docs/attributes/AsRequest.md',
    path: '/api/users',
    methods: ['POST'],
    name: 'api.users.create'
)]
class CreateUserRequest implements RequestInterface
{
    public string $email;
    public string $name;
}
```

### RESTful API з параметрами

```php
#[AsRequest(
    doc: 'docs/attributes/AsRequest.md',
    path: '/api/users/{id}',
    methods: ['GET', 'PUT', 'DELETE'],
    requirements: ['id' => '\d+'],
    defaults: ['id' => null]
)]
class UserRequest implements RequestInterface
{
    public ?int $id = null;
}
```

## Вимоги

1. Клас повинен реалізувати `RequestInterface`
2. Параметр `path` обов'язковий (якщо не використовується `base`)
3. Параметр `doc` обов'язковий та повинен вказувати на існуючий файл документації

## Див. також

- [AsRequestHandler](AsRequestHandler.md) - Створення handler для Request
- [AsResponse](AsResponse.md) - Створення Response DTO
- [Request/Response/Handler Pattern](../../CONVENTIONS.md) - Загальні конвенції

