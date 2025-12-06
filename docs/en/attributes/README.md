# Attributes Documentation

Ця директорія містить документацію для всіх атрибутів фреймворку Syntexa.

## Структура

Кожен атрибут має свій файл документації, на який посилається через обов'язковий параметр `doc`:

```php
#[AsRequest(
    doc: 'docs/attributes/AsRequest.md',  // ← Посилання на документацію
    path: '/api/users',
    methods: ['GET']
)]
class UserListRequest implements RequestInterface {}
```

## Доступні атрибути

### Core Attributes

- [AsRequest](AsRequest.md) - HTTP Request DTO
- [AsRequestHandler](AsRequestHandler.md) - HTTP Request Handler
- [AsResponse](AsResponse.md) - HTTP Response DTO
- [AsRequestPart](AsRequestPart.md) - Request extension trait

### ORM Attributes

- [AsEntity](AsEntity.md) - Database Entity
- [AsEntityPart](AsEntityPart.md) - Entity extension trait
- [Column](Column.md) - Database column mapping
- [Id](Id.md) - Primary key
- [GeneratedValue](GeneratedValue.md) - Auto-generated value
- [TimestampColumn](TimestampColumn.md) - Timestamp columns

## Використання для AI

AI асистенти можуть автоматично читати документацію з атрибутів через `AttributeDocReader`:

```php
use Syntexa\Core\Attributes\AttributeDocReader;

// Отримати документацію для класу
$reflection = new \ReflectionClass(UserListRequest::class);
$docs = AttributeDocReader::readClassAttributeDocs($reflection, $projectRoot);

// Отримати шлях до документації
$attr = $reflection->getAttributes(AsRequest::class)[0]->newInstance();
$docPath = AttributeDocReader::getDocPath($attr);
```

## Створення нової документації

При створенні нового атрибута:

1. Створіть файл документації в `docs/attributes/`
2. Додайте обов'язковий параметр `doc` до конструктора атрибута
3. Реалізуйте `DocumentedAttributeInterface` або використайте `DocumentedAttributeTrait`
4. Заповніть документацію з прикладами використання

### Шаблон документації

```markdown
# AttributeName

## Опис

Короткий опис що робить атрибут.

## Використання

```php
// Приклад коду
```

## Параметри

### Обов'язкові
- `param` - Опис

### Опціональні
- `param` - Опис

## Приклади

### Базовий приклад
```php
// Код
```

## Вимоги

1. Вимога 1
2. Вимога 2

## Пов'язані атрибути

- [OtherAttribute](OtherAttribute.md)

## Див. також

- [Related Documentation](../README.md)
```

## Переваги

✅ **Для AI**: Автоматичне читання документації з атрибутів  
✅ **Для розробників**: Документація завжди поруч з кодом  
✅ **Для IDE**: Можливість створення автодоповнення на основі документації  
✅ **Валідація**: Можна перевіряти наявність документації під час розробки

