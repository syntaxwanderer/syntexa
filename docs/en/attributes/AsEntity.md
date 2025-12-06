# AsEntity Attribute

## Опис

Атрибут `#[AsEntity]` позначає клас як Entity (сутність бази даних) для ORM. Entity представляє таблицю в базі даних та використовується для роботи з даними через EntityManager.

## Використання

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

## Параметри

### Обов'язкові

- `doc` (string) - Шлях до файлу документації (відносно кореня проекту)

### Опціональні

- `table` (string|null) - Назва таблиці в базі даних (за замовчуванням: автоматично з назви класу)
- `schema` (string|null) - Схема бази даних (для PostgreSQL)

## Вимоги

1. Клас повинен наслідувати `BaseEntity`
2. Клас повинен мати принаймні один `#[Id]` атрибут на властивості
3. Властивості повинні мати атрибут `#[Column]`
4. Параметр `doc` обов'язковий та повинен вказувати на існуючий файл документації

## Автоматична назва таблиці

Якщо параметр `table` не вказано, назва таблиці генерується автоматично з назви класу:

- `User` → `users` (додається 's' та перетворюється в snake_case)
- `OrderItem` → `order_items`
- `UserProfile` → `user_profiles`

## Приклади

### Базова Entity

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

### Entity зі схемою

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

### Entity з timestamps

```php
use Syntexa\Orm\Entity\Traits\TimestampedEntityTrait;

#[AsEntity(
    doc: 'docs/attributes/AsEntity.md',
    table: 'posts'
)]
class Post extends BaseEntity
{
    use TimestampedEntityTrait; // Додає created_at та updated_at

    #[Id]
    #[GeneratedValue(doc: 'docs/attributes/GeneratedValue.md')]
    #[Column(doc: 'docs/attributes/Column.md', name: 'id', type: 'integer')]
    private ?int $id = null;

    #[Column(doc: 'docs/attributes/Column.md', name: 'title', type: 'string')]
    private string $title;
}
```

## Розширення через Traits

Entity можна розширювати через traits з атрибутом `#[AsEntityPart]`:

```php
// Base entity в модулі
#[AsEntity(
    doc: 'docs/attributes/AsEntity.md',
    table: 'users'
)]
class User extends BaseEntity { }

// Extension trait в іншому модулі
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

## Використання з EntityManager

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

## Пов'язані атрибути

- `#[Id]` - Позначає властивість як первинний ключ
- `#[GeneratedValue]` - Автоматична генерація значення
- `#[Column]` - Маппінг на колонку бази даних
- `#[TimestampColumn]` - Timestamp колонки (created_at, updated_at)
- `#[AsEntityPart]` - Trait для розширення Entity

## Див. також

- [Column](Column.md) - Маппінг властивостей на колонки
- [AsEntityPart](AsEntityPart.md) - Розширення Entity через traits
- [EntityManager](../../../packages/syntexa/orm/README.md) - Документація ORM

