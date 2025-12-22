<?php

declare(strict_types=1);

namespace Syntexa\Orm\Mapping;

use ReflectionClass;
use Syntexa\Orm\Metadata\EntityMetadata;
use Syntexa\Orm\Metadata\RelationshipMetadata;
use Syntexa\Orm\Entity\EntityManager;

/**
 * Reflection-based mapper with selective, setter/constructor-driven mapping.
 */
class DefaultDomainMapper implements DomainMapperInterface
{
    public function __construct(
        private ?EntityManager $entityManager = null
    ) {
    }

    public function setEntityManager(EntityManager $em): void
    {
        $this->entityManager = $em;
    }

    public function toDomain(object $storage, EntityMetadata $metadata, DomainContext $context): object
    {
        $domainClass = $metadata->domainClass ?? $metadata->className;
        if ($domainClass === $metadata->className) {
            return $storage;
        }

        $data = $this->extractData($storage, $metadata);
        $domain = $this->instantiateDomain($domainClass, $data);
        $this->applySetters($domain, $data);

        // Handle relationships
        $this->mapRelationships($storage, $domain, $metadata, $context);

        $this->link($storage, $domain, $metadata, $context);
        return $domain;
    }

    public function toStorage(object $domain, object $storage, EntityMetadata $metadata, DomainContext $context): object
    {
        $data = $this->readDomainData($domain, $metadata);
        foreach ($metadata->columns as $column) {
            if (!array_key_exists($column->propertyName, $data)) {
                continue; // selective: only domain-exposed values
            }
            $column->setPhpValue($storage, $data[$column->propertyName]);
        }

        // Sync relationships (e.g. ManyToOne) from domain objects to storage FK fields
        $this->mapRelationshipsToStorage($domain, $storage, $metadata);

        $this->link($storage, $domain, $metadata, $context);
        return $storage;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractData(object $storage, EntityMetadata $metadata): array
    {
        $data = [];
        foreach ($metadata->columns as $column) {
            $value = $column->getValue($storage);
            $camel = $column->propertyName;
            $snake = $this->camelToSnake($camel);
            $data[$camel] = $value;
            $data[$snake] = $value;
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function instantiateDomain(string $domainClass, array $data): object
    {
        $ref = new ReflectionClass($domainClass);
        $ctor = $ref->getConstructor();

        if ($ctor === null || $ctor->getNumberOfParameters() === 0) {
            return $ref->newInstance();
        }

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $data)) {
                $args[] = $data[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $args[] = null;
            } else {
                throw new \RuntimeException("Cannot hydrate {$domainClass}: missing required constructor param \${$name}");
            }
        }

        return $ref->newInstanceArgs($args);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applySetters(object $domain, array $data): void
    {
        foreach ($data as $key => $value) {
            $setter = 'set' . $this->camelize($key);
            if (method_exists($domain, $setter)) {
                $domain->{$setter}($value);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readDomainData(object $domain, EntityMetadata $metadata): array
    {
        $result = [];
        $ref = new ReflectionClass($domain);
        foreach ($metadata->columns as $column) {
            $name = $column->propertyName;
            $getter = 'get' . ucfirst($name);
            $isser = 'is' . ucfirst($name);

            if ($ref->hasMethod($getter) && $ref->getMethod($getter)->isPublic()) {
                $result[$name] = $domain->{$getter}();
                continue;
            }
            if ($ref->hasMethod($isser) && $ref->getMethod($isser)->isPublic()) {
                $result[$name] = $domain->{$isser}();
                continue;
            }
            if ($ref->hasProperty($name)) {
                $prop = $ref->getProperty($name);
                if ($prop->isPublic()) {
                    $result[$name] = $prop->getValue($domain);
                }
            }
        }

        return $result;
    }

    /**
     * Map relationships from domain model back to storage (for save/update)
     *
     * Domain works with related objects (User, Address, etc.),
     * storage keeps only FK fields (user_id, address_id, ...).
     */
    private function mapRelationshipsToStorage(object $domain, object $storage, EntityMetadata $metadata): void
    {
        if (empty($metadata->relationships)) {
            return;
        }

        foreach ($metadata->relationships as $relationship) {
            // For now we support FK sync for owning sides: ManyToOne and OneToOne with join column
            if (!in_array($relationship->type, ['ManyToOne', 'OneToOne'], true)) {
                continue;
            }

            $this->syncForeignKeyFromDomain($domain, $storage, $relationship);
        }
    }

    /**
     * Sync a single relationship FK from domain related object to storage entity
     */
    private function syncForeignKeyFromDomain(object $domain, object $storage, RelationshipMetadata $relationship): void
    {
        // Domain property name, e.g. "user"
        $propertyName = $relationship->propertyName;
        $getter = 'get' . ucfirst($propertyName);

        if (!method_exists($domain, $getter)) {
            return; // Domain doesn't expose this relationship
        }

        $related = $domain->$getter();

        // Extract ID from related object (UserDomain or LazyProxy)
        $relatedId = $this->extractRelatedId($related);

        // Write FK into storage entity
        $this->setForeignKeyValue($storage, $relationship, $relatedId);
    }

    /**
     * Extract related entity ID from domain object or LazyProxy
     */
    private function extractRelatedId(mixed $related): ?int
    {
        if ($related === null) {
            return null;
        }

        // If it's a LazyProxy, use its getId()
        if ($related instanceof LazyProxy) {
            return $related->getId();
        }

        // If it's a domain/storage entity with getId()
        if (method_exists($related, 'getId')) {
            $id = $related->getId();
            return $id !== null ? (int) $id : null;
        }

        return null;
    }

    /**
     * Set foreign key value on storage entity based on relationship metadata
     */
    private function setForeignKeyValue(object $storage, RelationshipMetadata $relationship, ?int $id): void
    {
        $fkColumn = $relationship->getForeignKeyColumn();
        $reflection = new \ReflectionClass($storage);

        // Determine property name that holds FK in storage
        $candidateProps = [];

        if ($fkColumn !== null) {
            // Exact match from JoinColumn name
            $candidateProps[] = $fkColumn;
            // CamelCase version (user_id -> userId)
            $candidateProps[] = $this->snakeToCamel($fkColumn);
        }

        // Fallback: relationship property name + "Id" (user -> userId)
        $candidateProps[] = $relationship->propertyName . 'Id';

        foreach ($candidateProps as $propName) {
            if ($reflection->hasProperty($propName)) {
                $prop = $reflection->getProperty($propName);
                $prop->setAccessible(true);
                $prop->setValue($storage, $id);
                return;
            }
        }
    }

    /**
     * Map relationships from storage to domain
     */
    private function mapRelationships(object $storage, object $domain, EntityMetadata $metadata, DomainContext $context): void
    {
        if ($this->entityManager === null) {
            return; // Cannot map relationships without EntityManager
        }

        foreach ($metadata->relationships as $relationship) {
            $this->mapRelationship($storage, $domain, $relationship, $context);
        }
    }

    /**
     * Map a single relationship
     */
    private function mapRelationship(object $storage, object $domain, RelationshipMetadata $relationship, DomainContext $context): void
    {
        $fkValue = $this->getForeignKeyValue($storage, $relationship);
        
        if ($fkValue === null && !$relationship->joinColumn?->nullable) {
            return; // Non-nullable relationship without value
        }

        $propertyName = $relationship->propertyName;
        $setter = 'set' . ucfirst($propertyName);
        $getter = 'get' . ucfirst($propertyName);

        // Check if domain has setter for this relationship
        if (!method_exists($domain, $setter)) {
            return; // Domain doesn't expose this relationship
        }

        // Check if already set (avoid overwriting)
        if (method_exists($domain, $getter)) {
            $current = $domain->$getter();
            if ($current !== null) {
                return; // Already set
            }
        }

        if ($fkValue === null) {
            $domain->$setter(null);
            return;
        }

        // Resolve domain class for target entity
        // If targetEntity is a storage entity, resolve its domain class
        $targetDomainClass = $this->resolveDomainClass($relationship->targetEntity);
        
        // Create lazy proxy or eager load based on fetch strategy
        if ($relationship->isLazy() && $this->entityManager !== null) {
            $proxy = new LazyProxy(
                $this->entityManager,
                $targetDomainClass,
                $fkValue
            );
            $domain->$setter($proxy);
        } elseif ($relationship->isEager() && $this->entityManager !== null) {
            // Eager load - will be handled by EntityManager in find() with joins
            // For now, create lazy proxy (eager loading will be implemented in Phase 3)
            $proxy = new LazyProxy(
                $this->entityManager,
                $targetDomainClass,
                $fkValue
            );
            $domain->$setter($proxy);
        }
    }

    /**
     * Get foreign key value from storage entity
     */
    private function getForeignKeyValue(object $storage, RelationshipMetadata $relationship): ?int
    {
        $fkColumn = $relationship->getForeignKeyColumn();
        $reflection = new \ReflectionClass($storage);
        
        // First, try to find by join column name
        if ($fkColumn !== null) {
            // Try exact match first
            if ($reflection->hasProperty($fkColumn)) {
                $prop = $reflection->getProperty($fkColumn);
                $prop->setAccessible(true);
                $value = $prop->getValue($storage);
                return is_numeric($value) ? (int) $value : null;
            }
            
            // Try camelCase version (e.g., "user_id" -> "userId")
            $camelCase = $this->snakeToCamel($fkColumn);
            if ($reflection->hasProperty($camelCase)) {
                $prop = $reflection->getProperty($camelCase);
                $prop->setAccessible(true);
                $value = $prop->getValue($storage);
                return is_numeric($value) ? (int) $value : null;
            }
        }
        
        // Try to infer from property name (e.g., "user" -> "userId")
        $propertyName = $relationship->propertyName;
        $fkProperty = $propertyName . 'Id';
        
        if ($reflection->hasProperty($fkProperty)) {
            $prop = $reflection->getProperty($fkProperty);
            $prop->setAccessible(true);
            $value = $prop->getValue($storage);
            return is_numeric($value) ? (int) $value : null;
        }
        
        return null;
    }

    private function snakeToCamel(string $value): string
    {
        return lcfirst(str_replace('_', '', ucwords($value, '_')));
    }

    private function link(object $storage, object $domain, EntityMetadata $metadata, DomainContext $context): void
    {
        $sid = spl_object_id($storage);
        $did = spl_object_id($domain);
        $context->domainToStorage[$did] = $storage;
        $context->storageToDomain[$sid] = $domain;

        $identifier = $metadata->identifier;
        if ($identifier) {
            $idValue = $identifier->getValue($storage);
            if ($idValue !== null) {
                $key = $metadata->className . '#' . (string) $idValue;
                $context->storageById[$key] = $storage;
                $context->domainById[$key] = $domain;
            }
        }
    }

    private function camelToSnake(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    private function camelize(string $value): string
    {
        $value = str_replace(['_', '-'], ' ', $value);
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }

    /**
     * Resolve domain class for target entity
     * If targetEntity is a storage entity, return its domain class
     * If targetEntity is already a domain class, return it as is
     */
    private function resolveDomainClass(string $targetEntity): string
    {
        if ($this->entityManager === null) {
            return $targetEntity; // Cannot resolve without EntityManager
        }

        try {
            // Try to get metadata - if it works, it's a storage entity
            $metadata = $this->entityManager->getEntityMetadata($targetEntity);
            
            // If it has domainClass, return domain class
            if ($metadata->domainClass !== null) {
                return $metadata->domainClass;
            }
            
            // It's a storage entity without domainClass - this shouldn't happen in DDD
            // But for backward compatibility, return as is
            return $targetEntity;
        } catch (\RuntimeException) {
            // Not a storage entity, assume it's already a domain class
            return $targetEntity;
        }
    }
}

