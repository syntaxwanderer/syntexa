# Syntexa ORM

Stateless ORM for Syntexa Framework with PostgreSQL support and module table extension.

## Features

- ✅ **Stateless** - No state between requests (Swoole-safe)
- ✅ **Connection Pooling** - Uses Swoole PDOPool for PostgreSQL
- ✅ **Module Table Extension** - Extend tables via traits (like Request/Response)
- ✅ **Domain Extension** - Extend domain models via domain traits (no ORM attrs)
- ✅ **Async Support** - AsyncQueryBuilder for coroutine-based queries
- ✅ **Query Builder** - DQL-like syntax

## Installation

The ORM is already included in the framework. Configure database connection in `.env`:

```env
DB_HOST=localhost
DB_PORT=5432
DB_NAME=syntexa
DB_USER=postgres
DB_PASSWORD=your_password
DB_CHARSET=utf8
DB_POOL_SIZE=10
```

## Usage

### 1. Define Base Entity

```php
<?php

namespace Syntexa\UserFrontend\Domain\Entity;

use Syntexa\Orm\Attributes\AsEntity;
use Syntexa\Orm\Attributes\Column;
use Syntexa\Orm\Entity\BaseEntity;

#[AsEntity(table: 'users')]
class User extends BaseEntity
{
    #[Column(name: 'email', unique: true)]
    private string $email;

    #[Column(name: 'name', nullable: true)]
    private ?string $name = null;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
```

### 2. Extend Entity via Traits (Module Extension)

```php
<?php

namespace Acme\Marketing\Domain\Entity;

use Syntexa\Orm\Attributes\AsEntityPart;
use Syntexa\UserFrontend\Domain\Entity\User;

#[AsEntityPart(base: User::class)]
trait UserMarketingTrait
{
    public ?string $marketingTag;
    public ?string $referralCode;
}
```

### 3. Generate Entity Wrapper

```bash
bin/syntexa entity:generate User
# or
bin/syntexa entity:generate --all
```

This creates `src/modules/UserFrontend/Entity/User.php` that extends the base and uses traits.

### 4. Use EntityManager

```php
use Syntexa\Orm\Entity\EntityManager;
use DI\Attribute\Inject;

class UserRepository
{
    public function __construct(
        #[Inject] private EntityManager $em
    ) {}

    public function findByEmail(string $email): ?User
    {
        return $this->em->findOneBy(User::class, ['email' => $email]);
    }

    public function save(User $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }
}
```

### 5. Opt-in Timestamps

```php
use Syntexa\Orm\Entity\Traits\TimestampedEntityTrait;

#[AsEntity(table: 'orders')]
class Order extends BaseEntity
{
    use TimestampedEntityTrait;

    #[Column(type: 'string')]
    private string $number;

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }
}
```

EntityManager automatically fills `created_at` / `updated_at` columns when the properties exist, so mixing the trait in is all you need.

### 6. Query Builder

```php
$users = $em->createQueryBuilder()
    ->select('u.*')
    ->from(User::class, 'u')
    ->where('u.email = :email', $email)
    ->orderBy('u.createdAt', 'DESC')
    ->setMaxResults(10)
    ->getResult();
```

## Architecture

- **ConnectionPool** - Singleton, manages PostgreSQL connections via Swoole PDOPool
- **EntityManager** - Request-scoped, stateless, provides CRUD operations
- **BaseEntity** - Base class for common ID handling (attributes pre-configured)
- **Metadata** - Attribute-driven mapping (`#[Column]`, `#[Id]`, `#[TimestampColumn]`)
- **QueryBuilder** - DQL-like query builder
- **AsyncQueryBuilder** - For coroutine-based async queries

## Module Extension Pattern

Just like Request/Response wrappers, entities can be extended by other modules:

1. Base module declares `#[AsEntity]` class
2. Other modules declare `#[AsEntityPart]` traits
3. Generator creates wrapper in `src/modules/` that combines base + traits
4. Wrapper is used in application code

This allows modules to extend database tables without modifying base module code.

