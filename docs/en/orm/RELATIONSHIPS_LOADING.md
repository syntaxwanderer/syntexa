# Technical Design: Relationship Loading for Syntexa ORM

## Current State

**Problem**: Relationships work only through IDs (foreign keys). ORM doesn't automatically load related objects.

**Example**:
```php
$post = $em->find(PostStorage::class, 1);
// $post->getUserId() returns int (ID)
// To get User object, you need to manually:
$user = $em->find(UserStorage::class, $post->getUserId());
```

## Goal

Enable automatic loading of related entities:
- **Lazy Loading**: Load related object on first access (via proxy)
- **Eager Loading**: Load related objects immediately via JOIN
- **Domain Projection**: Related objects should be domain objects when `domainClass` is set

## Proposed Solution

### 1. Store Relationship Metadata

Extend `EntityMetadata` to include relationship information:

```php
class EntityMetadata
{
    // ... existing fields ...
    
    /**
     * @var array<string, RelationshipMetadata>
     */
    public readonly array $relationships;
}

class RelationshipMetadata
{
    public function __construct(
        public readonly string $propertyName,
        public readonly string $type, // 'OneToOne', 'OneToMany', 'ManyToOne', 'ManyToMany'
        public readonly string $targetEntity,
        public readonly ?string $mappedBy, // inverse side property name
        public readonly ?JoinColumnMetadata $joinColumn,
        public readonly string $fetch = 'lazy', // 'lazy' | 'eager'
        public readonly array $cascade = [],
    ) {
    }
}
```

### 2. Extract Relationship Metadata

Update `EntityMetadataFactory` to read relationship attributes:

```php
// In EntityMetadataFactory::buildMetadata()
$relationships = [];

foreach ($reflection->getProperties() as $property) {
    // Check for OneToOne, OneToMany, ManyToOne, ManyToMany attributes
    $oneToOne = $property->getAttributes(OneToOne::class)[0] ?? null;
    if ($oneToOne) {
        $attr = $oneToOne->newInstance();
        $joinColumn = $this->extractJoinColumn($property);
        
        $relationships[$property->getName()] = new RelationshipMetadata(
            propertyName: $property->getName(),
            type: 'OneToOne',
            targetEntity: $attr->targetEntity,
            mappedBy: $attr->mappedBy,
            joinColumn: $joinColumn,
            fetch: $attr->fetch,
            cascade: $attr->cascade,
        );
    }
    // ... similar for OneToMany, ManyToOne, ManyToMany
}
```

### 3. Lazy Loading via Proxy

Create proxy objects that load related entity on first access:

```php
class LazyProxy
{
    private ?object $entity = null;
    
    public function __construct(
        private EntityManager $em,
        private string $entityClass,
        private int $id,
    ) {
    }
    
    public function __call(string $method, array $args): mixed
    {
        if ($this->entity === null) {
            $this->entity = $this->em->find($this->entityClass, $this->id);
        }
        return $this->entity->$method(...$args);
    }
    
    public function getEntity(): object
    {
        if ($this->entity === null) {
            $this->entity = $this->em->find($this->entityClass, $this->id);
        }
        return $this->entity;
    }
}
```

### 4. Update Domain Mapper

Extend `DefaultDomainMapper` to handle relationships:

```php
// In toDomain()
if ($metadata->relationships[$propertyName] ?? null) {
    $relationship = $metadata->relationships[$propertyName];
    $fkValue = $this->getForeignKeyValue($storage, $relationship);
    
    if ($fkValue !== null) {
        if ($relationship->fetch === 'lazy') {
            // Create lazy proxy
            $related = new LazyProxy(
                $this->em,
                $relationship->targetEntity,
                $fkValue
            );
        } else {
            // Eager load
            $related = $this->em->find($relationship->targetEntity, $fkValue);
        }
        
        // Set on domain object
        $setter = 'set' . ucfirst($propertyName);
        if (method_exists($domain, $setter)) {
            $domain->$setter($related);
        }
    }
}
```

### 5. Eager Loading via JOIN

For `fetch: 'eager'`, modify `EntityManager::find()` to automatically JOIN:

```php
public function find(string $entityClass, int $id): ?object
{
    $metadata = $this->getEntityMetadata($entityClass);
    
    // Build query with eager relationships
    $query = "SELECT * FROM {$metadata->tableName}";
    $eagerJoins = [];
    
    foreach ($metadata->relationships as $rel) {
        if ($rel->fetch === 'eager' && $rel->type === 'ManyToOne') {
            $targetMetadata = $this->getEntityMetadata($rel->targetEntity);
            $eagerJoins[] = "LEFT JOIN {$targetMetadata->tableName} ON ...";
        }
    }
    
    // ... execute query and hydrate ...
}
```

### 6. Domain Model Support

For domain models to use relationships, they need properties:

```php
class PostDomain
{
    private ?int $id = null;
    private string $title;
    
    // Option 1: Store ID (current approach)
    private ?int $userId = null;
    
    // Option 2: Store related object (new approach)
    private ?UserDomain $user = null;
    
    public function getUser(): ?UserDomain
    {
        return $this->user;
    }
    
    public function setUser(?UserDomain $user): void
    {
        $this->user = $user;
        $this->userId = $user?->getId();
    }
}
```

## Implementation Phases

### Phase 1: Metadata Extraction
- Add relationship metadata to `EntityMetadata`
- Update `EntityMetadataFactory` to extract relationship attributes
- Store relationship info in metadata
- Add domain extension attribute `#[AsDomainPart(base: ...)]` for clean domain traits

### Phase 2: Lazy Loading
- Implement `LazyProxy` class
- Update mapper to create proxies for lazy relationships
- Test lazy loading works

### Phase 3: Eager Loading
- Modify `find()` and `findBy()` to support eager joins
- Update QueryBuilder to handle relationship joins automatically
- Test eager loading

### Phase 4: Domain Projection
- Ensure related objects are domain objects when `domainClass` is set
- Update mapper to project related entities
- Test domain projection with relationships

### Phase 5: Collections (OneToMany, ManyToMany)
- Implement lazy collection proxies
- Support eager loading of collections
- Handle bidirectional relationships

## Example Usage

### Before (Current)
```php
$post = $repo->find(1);
$userId = $post->getUserId(); // int
$user = $repo->find($userId); // Manual load
```

### After (Proposed)
```php
// Lazy loading
$post = $repo->find(1);
$user = $post->getUser(); // Automatically loads UserDomain on first access

// Eager loading
$post = $repo->find(1, ['eager' => ['user']]); // Loads user immediately via JOIN
```

## Considerations

1. **Stateless ORM**: In Swoole, each request gets new EntityManager. Proxies must work within single request scope.

2. **Performance**: 
   - Lazy loading: N+1 problem if not careful
   - Eager loading: More data loaded, but fewer queries

3. **Domain Model Design**:
   - Domain models need properties for related objects
   - Mapper must handle both ID and object properties

4. **Backward Compatibility**:
   - Keep ID-based access working
   - Add object-based access as enhancement

## Open Questions

1. Should we support both `$userId` and `$user` properties in domain model?
2. How to handle circular references in relationships?
3. Should we cache loaded relationships within request scope?
4. How to handle ManyToMany collections efficiently?

