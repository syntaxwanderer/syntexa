# Column Attribute

## Description

The `#[Column]` attribute marks an Entity property as a database column.  
It is used to map PHP properties to table columns.

## Usage

```php
use Syntexa\Orm\Attributes\Column;

#[AsEntity(doc: 'docs/attributes/AsEntity.md', table: 'users')]
class User extends BaseEntity
{
    #[Column(
        doc: 'docs/attributes/Column.md',
        name: 'email',
        type: 'string',
        unique: true,
        nullable: false
    )]
    private string $email;
}
```

## Parameters

### Required

- `doc` (string) - Path to the documentation file (relative to project root).

### Optional

- `name` (string|null) - Column name in the database (default: snake_case of property name).
- `type` (string) - Database type (default: `'string'`):
  - `'string'` - VARCHAR/TEXT
  - `'integer'` - INT/BIGINT
  - `'boolean'` - BOOLEAN
  - `'float'` - DECIMAL/FLOAT
  - `'datetime'` - TIMESTAMP/DATETIME
  - `'json'` - JSONB (PostgreSQL)
- `nullable` (bool) - Whether NULL is allowed (default: `false`).
- `unique` (bool) - Whether value must be unique (default: `false`).
- `length` (int|null) - Maximum length (for string type).
- `default` (mixed) - Default value.

## Data types

### String

```php
#[Column(
    doc: 'docs/attributes/Column.md',
    name: 'email',
    type: 'string',
    length: 255,
    unique: true
)]
private string $email;
```

### Integer

```php
#[Column(
    doc: 'docs/attributes/Column.md',
    name: 'age',
    type: 'integer',
    nullable: true
)]
private ?int $age = null;
```

### Boolean

```php
#[Column(
    doc: 'docs/attributes/Column.md',
    name: 'is_active',
    type: 'boolean',
    default: true
)]
private bool $isActive = true;
```

### DateTime

```php
#[Column(
    doc: 'docs/attributes/Column.md',
    name: 'created_at',
    type: 'datetime'
)]
private \DateTimeImmutable $createdAt;
```

### JSON

```php
#[Column(
    doc: 'docs/attributes/Column.md',
    name: 'metadata',
    type: 'json',
    nullable: true
)]
private ?array $metadata = null;
```

## Automatic column name

If `name` is not specified, the column name is generated automatically:

- `$email` → `email`
- `$firstName` → `first_name`
- `$isActive` → `is_active`

## Examples

### Required field

```php
#[Column(
    doc: 'docs/attributes/Column.md',
    name: 'email',
    type: 'string',
    nullable: false,
    unique: true
)]
private string $email;
```

### Optional field

```php
#[Column(
    doc: 'docs/attributes/Column.md',
    name: 'phone',
    type: 'string',
    nullable: true
)]
private ?string $phone = null;
```

### Field with default value

```php
#[Column(
    doc: 'docs/attributes/Column.md',
    name: 'status',
    type: 'string',
    default: 'pending'
)]
private string $status = 'pending';
```

### Field with length constraint

```php
#[Column(
    doc: 'docs/attributes/Column.md',
    name: 'title',
    type: 'string',
    length: 100
)]
private string $title;
```

## Requirements

1. The attribute MUST be used on properties of a class annotated with `#[AsEntity]`.
2. The `doc` parameter is required and MUST point to an existing documentation file.
3. The PHP property type SHOULD match the column type.

## Related attributes

- `#[AsEntity]` - Marks a class as an Entity.
- `#[Id]` - Marks a column as a primary key.
- `#[GeneratedValue]` - Automatic value generation.
- `#[TimestampColumn]` - Special timestamp columns.

## See also

- [AsEntity](AsEntity.md) - Creating an Entity.
- [Id](Id.md) - Primary key.
- [GeneratedValue](GeneratedValue.md) - Automatic generation.

