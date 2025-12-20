<?php

declare(strict_types=1);

namespace Syntexa\Orm\Repository;

use Syntexa\Orm\Entity\EntityManager;
use Syntexa\Orm\Metadata\EntityMetadataFactory;
use Syntexa\Orm\Query\QueryBuilder;

/**
 * Mapper-aware base repository: domain in/out, storage under the hood.
 * 
 * In DDD approach, repository works with domain entities only.
 * Storage entities are an implementation detail handled by ORM.
 */
class DomainRepository
{
    /**
     * Storage entity class (for ORM operations)
     */
    protected string $storageClass;
    
    /**
     * Domain entity class (what repository works with)
     */
    protected ?string $domainClass = null;

    /**
     * @param EntityManager $em EntityManager instance
     * @param string $entityClass Storage entity class OR domain entity class
     *                            If domain class is provided, storage will be resolved automatically
     */
    public function __construct(
        protected EntityManager $em,
        string $entityClass
    ) {
        // First, try to get metadata for the class - if it works, it's a storage class
        try {
            $metadata = $em->getEntityMetadata($entityClass);
            // It's a storage class
            $this->storageClass = $entityClass;
            $this->domainClass = $metadata->domainClass;
        } catch (\RuntimeException) {
            // Not a storage class, try to resolve as domain class
            // Use EntityMetadataFactory which has the discovery logic
            $storageClass = EntityMetadataFactory::resolveEntityClassForDomain($entityClass);
            
            if ($storageClass === null) {
                // Try our own discovery as fallback
                $storageClass = $this->resolveStorageForDomain($entityClass);
            }
            
            if ($storageClass === null) {
                throw new \RuntimeException(
                    "Cannot resolve entity class '{$entityClass}'. " .
                    "It must be either a storage entity with #[AsEntity] attribute, " .
                    "or a domain class with configured domainClass in #[AsEntity]. " .
                    "Make sure the storage entity class is autoloaded."
                );
            }
            
            // Entity class is a domain class, storage was resolved
            $this->domainClass = $entityClass;
            $this->storageClass = $storageClass;
        }
    }
    
    /**
     * Resolve storage class for domain class by scanning declared classes
     * 
     * @param string $domainClass Domain class name
     * @return string|null Storage class name or null if not found
     */
    private function resolveStorageForDomain(string $domainClass): ?string
    {
        // Try common naming patterns for storage class
        // e.g., Domain -> Storage, UserDomain -> UserStorage
        $possibleStorageClasses = [
            str_replace('\\Domain\\', '\\Storage\\', $domainClass),
            str_replace('\\Domain', '\\Storage', $domainClass),
            str_replace('Domain', 'Storage', $domainClass),
            str_replace('\\Domain\\', '\\Infrastructure\\Database\\', $domainClass),
        ];
        
        foreach ($possibleStorageClasses as $possibleClass) {
            if (class_exists($possibleClass)) {
                try {
                    $metadata = $this->em->getEntityMetadata($possibleClass);
                    if ($metadata->domainClass === $domainClass) {
                        return $possibleClass;
                    }
                } catch (\RuntimeException) {
                    // Not an entity, continue
                }
            }
        }
        
        // Last resort: scan all declared classes
        foreach (get_declared_classes() as $class) {
            if (!str_contains($class, '\\')) {
                continue;
            }
            
            try {
                $ref = new \ReflectionClass($class);
            } catch (\ReflectionException) {
                continue;
            }
            
            // Check if class has AsEntity attribute
            $attrs = $ref->getAttributes(\Syntexa\Orm\Attributes\AsEntity::class);
            if (empty($attrs)) {
                continue;
            }
            
            // Get domainClass from attribute
            $attr = $attrs[0]->newInstance();
            if ($attr->domainClass === $domainClass) {
                // Found it! Load metadata to populate cache
                $this->em->getEntityMetadata($class);
                return $class;
            }
        }
        
        return null;
    }

    /**
     * Create a new domain entity instance
     * 
     * In DDD approach, repository always works with domain entities.
     * This is the recommended way to create new entities instead of using `new DomainEntity()`.
     * 
     * Benefits:
     * - Always returns domain entity (DDD-compliant)
     * - No need to know storage class (implementation detail)
     * - Centralized entity creation (can add default values, validation, events)
     * - Consistent API across all repositories
     * 
     * @return object New domain entity instance
     * @throws \RuntimeException If domain class is not configured
     */
    public function create(): object
    {
        if ($this->domainClass === null) {
            throw new \RuntimeException(
                "Cannot create domain entity: domain class is not configured for storage class {$this->storageClass}. " .
                "Add domainClass parameter to #[AsEntity] attribute."
            );
        }
        
        return new ($this->domainClass)();
    }

    /**
     * Find domain entity by ID
     * 
     * @param int $id Entity ID
     * @return object|null Domain entity or null if not found
     */
    public function find(int $id): ?object
    {
        // Use domain class if available, otherwise storage class
        $entityClass = $this->domainClass ?? $this->storageClass;
        return $this->em->find($entityClass, $id);
    }

    /**
     * Find one domain entity by criteria
     * 
     * @param array $criteria Search criteria
     * @return object|null Domain entity or null if not found
     */
    public function findOneBy(array $criteria): ?object
    {
        $entityClass = $this->domainClass ?? $this->storageClass;
        return $this->em->findOneBy($entityClass, $criteria);
    }

    /**
     * Find domain entities by criteria
     * 
     * @param array $criteria Search criteria
     * @param array|null $orderBy Ordering (field => direction)
     * @param int|null $limit Maximum number of results
     * @param int|null $offset Offset for pagination
     * @return array Domain entities
     */
    public function findBy(array $criteria = [], ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $entityClass = $this->domainClass ?? $this->storageClass;
        return $this->em->findBy($entityClass, $criteria, $orderBy, $limit, $offset);
    }

    /**
     * Save entity (insert or update) - immediately writes to database
     * 
     * @param object $domain Domain entity
     * @return object Saved domain entity (with ID if new)
     */
    public function save(object $domain): object
    {
        return $this->em->save($domain);
    }

    /**
     * Update existing entity - immediately writes to database
     * 
     * @param object $domain Domain entity (must have ID)
     * @return object Updated domain entity
     * @throws \RuntimeException If entity doesn't have ID
     */
    public function update(object $domain): object
    {
        return $this->em->update($domain);
    }

    /**
     * Delete entity - immediately removes from database
     * 
     * @param object $domain Domain entity
     * @throws \RuntimeException If entity doesn't have ID
     */
    public function delete(object $domain): void
    {
        $this->em->delete($domain);
    }

    /**
     * @deprecated Use delete() instead
     */
    public function remove(object $domain): void
    {
        $this->delete($domain);
    }

    /**
     * Save entity asynchronously (Swoole only)
     * 
     * @param object $domain Domain entity
     * @return \Generator Yields the saved domain entity (with ID if new)
     * @throws \RuntimeException If Swoole is not available
     */
    public function saveAsync(object $domain): \Generator
    {
        return yield from $this->em->saveAsync($domain);
    }

    /**
     * Update entity asynchronously (Swoole only)
     * 
     * @param object $domain Domain entity (must have ID)
     * @return \Generator Yields the updated domain entity
     * @throws \RuntimeException If Swoole is not available or entity doesn't have ID
     */
    public function updateAsync(object $domain): \Generator
    {
        return yield from $this->em->updateAsync($domain);
    }

    /**
     * Delete entity asynchronously (Swoole only)
     * 
     * @param object $domain Domain entity
     * @return \Generator Yields void when complete
     * @throws \RuntimeException If Swoole is not available or entity doesn't have ID
     */
    public function deleteAsync(object $domain): \Generator
    {
        yield from $this->em->deleteAsync($domain);
    }

    /**
     * Create query builder for this repository's entity
     * 
     * @param string $alias Table alias
     * @return QueryBuilder
     */
    public function createQueryBuilder(string $alias = 'e'): QueryBuilder
    {
        // Query builder works with storage class (database table)
        return $this->em->createQueryBuilder()->from($this->storageClass, $alias);
    }
    
    /**
     * Get domain class that this repository works with
     * 
     * @return string|null Domain class name or null if not configured
     */
    public function getDomainClass(): ?string
    {
        return $this->domainClass;
    }
    
    /**
     * Get storage class used by ORM
     * 
     * @return string Storage class name
     */
    public function getStorageClass(): string
    {
        return $this->storageClass;
    }
}

