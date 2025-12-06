# Syntexa Framework Conventions

> **Status:** Draft - To be completed  
> **Location:** `docs/en/guides/CONVENTIONS.md`

This document defines coding conventions, patterns, and best practices for Syntexa Framework.

## Table of Contents

- [Module Structure](#module-structure)
- [Naming Conventions](#naming-conventions)
- [Request/Response/Handler Pattern](#requestresponsehandler-pattern)
- [Entity Patterns](#entity-patterns)
- [Attribute Usage](#attribute-usage)

## Module Structure

### Standard Module Layout

```
module-name/
├── composer.json          # Must have "type": "syntexa-module"
├── src/
│   ├── Application/
│   │   ├── Handler/
│   │   │   └── Request/   # HTTP handlers
│   │   ├── Input/
│   │   │   └── Http/      # Request DTOs
│   │   ├── Output/        # Response DTOs
│   │   └── View/
│   │       └── templates/  # Twig templates
│   └── Domain/
│       ├── Entity/        # Domain entities
│       ├── Repository/    # Data repositories
│       └── Service/        # Business logic
```

## Naming Conventions

### Request Classes
- Must end with `Request`
- Must have `#[AsRequest(path: '/path', methods: ['GET'])]` attribute
- Example: `UserListRequest`, `LoginFormRequest`

### Response Classes
- Must end with `Response`
- Must have `#[AsResponse(template: 'template.html.twig')]` attribute
- Example: `UserListResponse`, `DashboardResponse`

### Handler Classes
- Must end with `Handler`
- Must implement `HttpHandlerInterface`
- Must have `#[AsRequestHandler(for: RequestClass::class)]` attribute
- Example: `UserListHandler`, `LoginFormHandler`

### Entity Classes
- Must extend `BaseEntity`
- Must have `#[AsEntity(table: 'table_name')]` attribute
- Properties must have `#[Column(name: 'column_name')]` attribute
- Example: `User`, `Order`

## Request/Response/Handler Pattern

### Step 1: Create Request DTO

```php
#[AsRequest(path: '/api/users', methods: ['GET'])]
class UserListRequest implements RequestInterface
{
    // Request properties
}
```

### Step 2: Create Response DTO

```php
#[AsResponse(template: 'users/list.html.twig')]
class UserListResponse implements ResponseInterface
{
    // Response properties
}
```

### Step 3: Create Handler

```php
#[AsRequestHandler(for: UserListRequest::class)]
class UserListHandler implements HttpHandlerInterface
{
    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Handler logic
        return $response;
    }
}
```

### Step 4: Generate Wrappers (if needed)

```bash
bin/syntexa request:generate UserListRequest
bin/syntexa response:generate UserListResponse
```

## Entity Patterns

### Base Entity

```php
#[AsEntity(table: 'users')]
class User extends BaseEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'id', type: 'integer')]
    private ?int $id = null;
    
    #[Column(name: 'email', type: 'string')]
    private string $email;
}
```

### Entity Extension via Traits

```php
// Base entity (in module)
#[AsEntity(table: 'users')]
class User extends BaseEntity { }

// Extension trait (in another module)
#[AsEntityPart(base: User::class)]
trait UserMarketingTrait
{
    public ?string $marketingTag;
}
```

### Generate Entity Wrapper

```bash
bin/syntexa entity:generate User
```

This creates `src/infrastructure/database/User.php` that combines base + traits.

## Attribute Usage

### Documentation Attribute

All framework attributes should have documentation:

```php
#[Documentation]  // Auto-finds docs/en/attributes/AsRequest.md
#[AsRequest(path: '/api/users')]
class UserListRequest implements RequestInterface {}
```

Or with explicit path:

```php
#[Documentation(path: 'docs/en/attributes/AsRequest.md')]
#[AsRequest(path: '/api/users')]
class UserListRequest implements RequestInterface {}
```

---

**Note:** This document is a work in progress. More conventions will be added as the framework evolves.

