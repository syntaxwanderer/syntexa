# AsEntity Attribute

## Description

The `#[AsEntity]` attribute marks a class as a database Entity for the ORM.  
An Entity represents a database table and is used by the `EntityManager` to work with data.

## Usage

```php
use Syntexa\Orm\Attributes\AsEntity;
use Syntexa\Orm\Attributes\Column;
use Syntexa\Orm\Attributes\Id;
use Syntexa\Orm\Attributes\GeneratedValue;
use Syntexa\Orm\Entity\BaseEntity;

#[AsEntity(
    doc: 'docs/attributes/AsEntity.md',
    table: 'users'
)]
class User extends BaseEntity
{
    #[Id]
    #[GeneratedValue(doc: 'docs/attributes/GeneratedValue.md')]
    #[Column(doc: 'docs/attributes/Column.md', name: 'id', type: 'integer')]
    private ?int $id = null;

    #[Column(doc: 'docs/attributes/Column.md', name: 'email', type: 'string', unique: true)]
    private string $email;

    #[Column(doc: 'docs/attributes/Column.md', name: 'name', type: 'string', nullable: true)]
    private ?string $name = null;

    // Getters and setters...
}
```

## Parameters

### Required

- `doc` (string) - Path to the documentation file (relative to project root).

### Optional

- `table` (string|null) - Database table name (default: generated from class name).
- `schema` (string|null) - Database schema (for PostgreSQL).

## Requirements

1. Class SHOULD extend `BaseEntity` (or be compatible with the ORM expectations).
2. Class MUST have at least one property with `#[Id]`.
3. Mapped properties MUST have the `#[Column]` attribute.
4. The `doc` parameter is required and MUST point to an existing documentation file.

## Automatic table name

If the `table` parameter is not specified, the table name is generated from the class name:

- `User` → `users` (snake_case + pluralized with `s`)
- `OrderItem` → `order_items`
- `UserProfile` → `user_profiles`

## Examples

### Basic Entity

```php
#[AsEntity(
    doc: 'docs/attributes/AsEntity.md',
    table: 'users'
)]
class User extends BaseEntity
{
    #[Id]
    #[GeneratedValue(doc: 'docs/attributes/GeneratedValue.md')]
    #[Column(doc: 'docs/attributes/Column.md', name: 'id', type: 'integer')]
    private ?int $id = null;

    #[Column(doc: 'docs/attributes/Column.md', name: 'email', type: 'string')]
    private string $email;
}
```

### Entity with schema

```php
#[AsEntity(
    doc: 'docs/attributes/AsEntity.md',
    table: 'orders',
    schema: 'ecommerce'
)]
class Order extends BaseEntity
{
    #[Id]
    #[GeneratedValue(doc: 'docs/attributes/GeneratedValue.md')]
    #[Column(doc: 'docs/attributes/Column.md', name: 'id', type: 'integer')]
    private ?int $id = null;
}
```

### Entity with timestamps

```php
use Syntexa\Orm\Entity\Traits\TimestampedEntityTrait;

#[AsEntity(
    doc: 'docs/attributes/AsEntity.md',
    table: 'posts'
)]
class Post extends BaseEntity
{
    use TimestampedEntityTrait; // Adds created_at and updated_at

    #[Id]
    #[GeneratedValue(doc: 'docs/attributes/GeneratedValue.md')]
    #[Column(doc: 'docs/attributes/Column.md', name: 'id', type: 'integer')]
    private ?int $id = null;

    #[Column(doc: 'docs/attributes/Column.md', name: 'title', type: 'string')]
    private string $title;
}
```

## Extension via Traits

Entities can be extended via traits annotated with `#[AsEntityPart]`:

```php
// Base entity in module
#[AsEntity(
    doc: 'docs/attributes/AsEntity.md',
    table: 'users'
)]
class User extends BaseEntity { }

// Extension trait in another module
#[AsEntityPart(
    doc: 'docs/attributes/AsEntityPart.md',
    base: User::class
)]
trait UserMarketingTrait
{
    public ?string $marketingTag;
    public ?string $referralCode;
}
```

## Using with EntityManager

```php
use Syntexa\Orm\Entity\EntityManager;
use DI\Attribute\Inject;

class UserRepository
{
    public function __construct(
        #[Inject] private EntityManager $em
    ) {}

    public function findById(int $id): ?User
    {
        return $this->em->find(User::class, $id);
    }

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

## Related attributes

- `#[Id]` - Marks a property as a primary key.
- `#[GeneratedValue]` - Automatic value generation.
- `#[Column]` - Maps a property to a database column.
- `#[TimestampColumn]` - Timestamp columns (created_at, updated_at).
- `#[AsEntityPart]` - Trait for extending an Entity.

## See also

- [Column](Column.md) - Property-to-column mapping.
- [AsEntityPart](AsEntityPart.md) - Extending Entity via traits.
- [EntityManager](../../../packages/syntexa/orm/README.md) - ORM documentation.

