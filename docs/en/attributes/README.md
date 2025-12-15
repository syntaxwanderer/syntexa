# Attributes Documentation

This directory contains documentation for all attributes used in the Syntexa framework.

## Structure

Each attribute has its own documentation file, referenced via the required `doc` parameter:

```php
#[AsRequest(
    doc: 'docs/attributes/AsRequest.md',  // ← Documentation link
    path: '/api/users',
    methods: ['GET']
)]
class UserListRequest implements RequestInterface {}
```

## Available attributes

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

## Usage for AI

AI assistants can automatically read attribute documentation via `AttributeDocReader`:

```php
use Syntexa\Core\Attributes\AttributeDocReader;

// Get documentation for a class
$reflection = new \ReflectionClass(UserListRequest::class);
$docs = AttributeDocReader::readClassAttributeDocs($reflection, $projectRoot);

// Get documentation path from attribute
$attr = $reflection->getAttributes(AsRequest::class)[0]->newInstance();
$docPath = AttributeDocReader::getDocPath($attr);
```

## Creating new attribute documentation

When creating a new attribute:

1. Create a documentation file in `docs/attributes/`.
2. Add a required `doc` parameter to the attribute constructor.
3. Implement `DocumentedAttributeInterface` or use `DocumentedAttributeTrait`.
4. Fill the documentation with usage and examples.

### Documentation template

```markdown
# AttributeName

## Description

Short description of what the attribute does.

## Usage

```php
// Example code
```

## Parameters

### Required
- `param` - Description

### Optional
- `param` - Description

## Examples

### Basic example
```php
// Code
```

## Requirements

1. Requirement 1
2. Requirement 2

## Related attributes

- [OtherAttribute](OtherAttribute.md)

## See also

- [Related Documentation](../README.md)
```

## Benefits

✅ **For AI**: Automatic documentation lookup from attributes  
✅ **For developers**: Documentation is always close to the code  
✅ **For IDEs**: Enables autocomplete and hints based on documentation  
✅ **Validation**: You can enforce presence of documentation during development

