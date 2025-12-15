# AsRequest Attribute

## Description

The `#[AsRequest]` attribute marks a class as an HTTP Request DTO (Data Transfer Object)  
and defines the route, HTTP methods and other routing parameters.

## Usage

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

# Parameters

### Required

- `doc` (string) - Path to the documentation file (relative to project root).

### Optional

- `base` (string|null) - Base Request class to inherit from.
- `responseWith` (string|null) - Response class to be used for this Request.
- `path` (string|null) - Route URL path (required if `base` is not provided).
- `methods` (array|null) - HTTP methods (default: `['GET']`).
- `name` (string|null) - Route name (default: short class name).
- `requirements` (array|null) - Route parameter requirements.
- `defaults` (array|null) - Default values for route parameters.
- `options` (array|null) - Additional route options.
- `tags` (array|null) - Tags/metadata for the route.
- `public` (bool|null) - Whether the route is public (default: `true`).

## Environment Variables

You can use environment variables in any attribute value:

- `env::VAR_NAME` - читає з .env файлу, повертає порожній рядок якщо не встановлено
- `env::VAR_NAME::default_value` - читає з .env файлу, повертає default якщо не встановлено (рекомендовано)
- `env::VAR_NAME:default_value` - старий формат, також підтримується

**It is recommended to use the double colon (`::`) syntax**,  
because it allows colons in default values.

### Example with environment variables:

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

## Inheritance via `base`

You can inherit parameters from another Request:

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
    path: '/users'  // Overrides path, inherits methods
)]
class UserListRequest extends BaseApiRequest {}
```

## Related attributes

- `#[AsRequestHandler]` - Handler for processing the Request.
- `#[AsRequestPart]` - Trait to extend a Request.
- `#[AsResponse]` - Response DTO.

## Examples

### Basic GET request

```php
#[AsRequest(
    doc: 'docs/attributes/AsRequest.md',
    path: '/dashboard',
    methods: ['GET']
)]
class DashboardRequest implements RequestInterface {}
```

### POST request with validation

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

### RESTful API with parameters

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

## Requirements

1. Class MUST implement `RequestInterface`.
2. The `path` parameter is required (unless `base` is used).
3. The `doc` parameter is required and MUST point to an existing documentation file.

## See also

- [AsRequestHandler](AsRequestHandler.md) - Creating a handler for a Request.
- [AsResponse](AsResponse.md) - Creating a Response DTO.
- [Request/Response/Handler Pattern](../../CONVENTIONS.md) - General conventions.

