# Column Attribute

## Опис

Атрибут `#[Column]` позначає властивість класу Entity як колонку бази даних. Використовується для маппінгу PHP властивостей на колонки таблиці.

## Використання

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

## Параметри

### Обов'язкові

- `doc` (string) - Шлях до файлу документації (відносно кореня проекту)

### Опціональні

- `name` (string|null) - Назва колонки в базі даних (за замовчуванням: snake_case від назви властивості)
- `type` (string) - Тип даних в базі (за замовчуванням: `'string'`):
  - `'string'` - VARCHAR/TEXT
  - `'integer'` - INT/BIGINT
  - `'boolean'` - BOOLEAN
  - `'float'` - DECIMAL/FLOAT
  - `'datetime'` - TIMESTAMP/DATETIME
  - `'json'` - JSONB (PostgreSQL)
- `nullable` (bool) - Чи може бути NULL (за замовчуванням: `false`)
- `unique` (bool) - Чи має бути унікальним (за замовчуванням: `false`)
- `length` (int|null) - Максимальна довжина (для string типу)
- `default` (mixed) - Значення за замовчуванням

## Типи даних

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

## Автоматична назва колонки

Якщо параметр `name` не вказано, назва колонки генерується автоматично:

- `$email` → `email`
- `$firstName` → `first_name`
- `$isActive` → `is_active`

## Приклади

### Обов'язкове поле

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

### Опціональне поле

```php
#[Column(
    doc: 'docs/attributes/Column.md',
    name: 'phone',
    type: 'string',
    nullable: true
)]
private ?string $phone = null;
```

### Поле з значенням за замовчуванням

```php
#[Column(
    doc: 'docs/attributes/Column.md',
    name: 'status',
    type: 'string',
    default: 'pending'
)]
private string $status = 'pending';
```

### Поле з обмеженням довжини

```php
#[Column(
    doc: 'docs/attributes/Column.md',
    name: 'title',
    type: 'string',
    length: 100
)]
private string $title;
```

## Вимоги

1. Атрибут повинен використовуватися на властивостях класу з `#[AsEntity]`
2. Параметр `doc` обов'язковий та повинен вказувати на існуючий файл документації
3. Тип PHP властивості повинен відповідати типу колонки

## Пов'язані атрибути

- `#[AsEntity]` - Позначає клас як Entity
- `#[Id]` - Позначає колонку як первинний ключ
- `#[GeneratedValue]` - Автоматична генерація значення
- `#[TimestampColumn]` - Спеціальні timestamp колонки

## Див. також

- [AsEntity](AsEntity.md) - Створення Entity
- [Id](Id.md) - Первинний ключ
- [GeneratedValue](GeneratedValue.md) - Автоматична генерація

