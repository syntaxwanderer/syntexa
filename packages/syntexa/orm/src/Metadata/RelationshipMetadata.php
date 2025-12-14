<?php

declare(strict_types=1);

namespace Syntexa\Orm\Metadata;

/**
 * Metadata for entity relationships
 */
class RelationshipMetadata
{
    /**
     * @param string $propertyName Property name in the entity
     * @param string $type Relationship type: 'OneToOne', 'OneToMany', 'ManyToOne', 'ManyToMany'
     * @param string $targetEntity Target entity class name
     * @param string|null $mappedBy Inverse side property name (for bidirectional relationships)
     * @param JoinColumnMetadata|null $joinColumn Join column information
     * @param string $fetch Fetch strategy: 'lazy' | 'eager'
     * @param array $cascade Cascade operations: ['persist', 'remove']
     * @param bool $orphanRemoval Whether to remove orphaned entities
     */
    public function __construct(
        public readonly string $propertyName,
        public readonly string $type,
        public readonly string $targetEntity,
        public readonly ?string $mappedBy = null,
        public readonly ?JoinColumnMetadata $joinColumn = null,
        public readonly string $fetch = 'lazy',
        public readonly array $cascade = [],
        public readonly bool $orphanRemoval = false,
    ) {
    }

    /**
     * Get the foreign key column name for this relationship
     */
    public function getForeignKeyColumn(): ?string
    {
        return $this->joinColumn?->name;
    }

    /**
     * Check if this relationship should be eagerly loaded
     */
    public function isEager(): bool
    {
        return $this->fetch === 'eager';
    }

    /**
     * Check if this relationship should be lazily loaded
     */
    public function isLazy(): bool
    {
        return $this->fetch === 'lazy';
    }
}

