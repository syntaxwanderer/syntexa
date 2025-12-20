# Syntexa ORM

Stateless ORM for Syntexa Framework with PostgreSQL support and module table extension.

## Features

- ✅ **Stateless** - No state between requests (Swoole-safe)
- ✅ **Connection Pooling** - Uses Swoole PDOPool for PostgreSQL
- ✅ **Module Table Extension** - Extend tables via traits (like Request/Response)
- ✅ **Domain Extension** - Extend domain models via domain traits (no ORM attrs)
- ✅ **Direct Operations** - No Unit of Work pattern - save/update/delete write immediately
- ✅ **Async Support** - Async operations via Swoole coroutines (saveAsync, updateAsync, deleteAsync)
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

### 4. Use Repository (Recommended - DDD Approach)

In DDD approach, repository works with **domain entities only**. Storage entities are an implementation detail.

```php
use Syntexa\Orm\Repository\DomainRepository;
use Syntexa\Orm\Entity\EntityManager;
use Syntexa\UserFrontend\Domain\Entity\User;
use DI\Attribute\Inject;

class UserRepository extends DomainRepository
{
    public function __construct(
        #[Inject] EntityManager $em
    ) {
        // Pass domain class - repository will resolve storage automatically
        parent::__construct($em, User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    // Create new domain entity (DDD-compliant)
    public function create(): User
    {
        return parent::create();
    }

    // save() immediately writes to database (no flush needed)
    public function save(User $user): User
    {
        return parent::save($user);
    }
}
```

**Usage:**

```php
$repo = $container->get(UserRepository::class);

// Create new domain entity (no need to know storage class)
$user = $repo->create();
$user->setEmail('alice@example.com');
$user->setName('Alice');

// Save immediately writes to database
$saved = $repo->save($user);

// All operations work with domain entities
$found = $repo->find($saved->getId());
```

**Important:** `EntityManager` works **only with domain entities**. Storage entities cannot be used directly - this ensures proper separation between domain and infrastructure layers. Always use repositories or pass domain classes to `EntityManager` methods.

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
        // EntityManager accepts domain class, not storage entity
        return $this->em->findOneBy(User::class, ['email' => $email]);
    }

    // save() immediately writes to database
    // EntityManager automatically maps domain to storage
    public function save(User $user): User
    {
        return $this->em->save($user);
    }

    // update() for existing entities
    public function update(User $user): User
    {
        return $this->em->update($user);
    }

    // delete() immediately removes from database
    public function delete(User $user): void
    {
        $this->em->delete($user);
    }
}
```

**Note:** If you try to use a storage entity directly with `EntityManager`, it will throw an exception. This enforces DDD principles and prevents infrastructure concerns from leaking into the domain layer.

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

### 7. Async Operations (Swoole Only)

In Swoole environment, you can use async operations for non-blocking database writes:

```php
use Swoole\Coroutine;

// Save multiple entities in parallel
$coroutines = [];
foreach ($users as $user) {
    $coroutines[] = Coroutine::create(function () use ($user, $repo) {
        yield from $repo->saveAsync($user);
    });
}

// Wait for all to complete
foreach ($coroutines as $coroutine) {
    $coroutine->join();
}
```

Or use async methods directly:

```php
// Async save
$generator = $repo->saveAsync($user);
$saved = $generator->current();

// Async update
$generator = $repo->updateAsync($user);
$updated = $generator->current();

// Async delete
$generator = $repo->deleteAsync($user);
$generator->next(); // Wait for completion
```

## Architecture

- **ConnectionPool** - Singleton, manages PostgreSQL connections via Swoole PDOPool
- **EntityManager** - Request-scoped, stateless, provides direct CRUD operations (save/update/delete)
- **DomainRepository** - Base repository class with domain-focused API
- **BaseEntity** - Base class for common ID handling (attributes pre-configured)
- **Metadata** - Attribute-driven mapping (`#[Column]`, `#[Id]`, `#[TimestampColumn]`)
- **QueryBuilder** - DQL-like query builder
- **AsyncQueryBuilder** - For coroutine-based async queries

## Key Design Decisions

### No Unit of Work Pattern

Unlike Doctrine, Syntexa ORM uses **direct operations** instead of `persist()`/`flush()`:

- ✅ **Immediate writes** - `save()`, `update()`, `delete()` write to database immediately
- ✅ **Simpler API** - No need to remember to call `flush()`
- ✅ **Better for Swoole** - Stateless operations fit perfectly with request-scoped architecture
- ✅ **Async support** - Operations can be made async via `saveAsync()`, `updateAsync()`, `deleteAsync()`

This approach is more intuitive and aligns with how developers actually use ORMs in practice.

## Module Extension Pattern

Just like Request/Response wrappers, entities can be extended by other modules:

1. Base module declares `#[AsEntity]` class
2. Other modules declare `#[AsEntityPart]` traits
3. Generator creates wrapper in `src/modules/` that combines base + traits
4. Wrapper is used in application code

This allows modules to extend database tables without modifying base module code.

