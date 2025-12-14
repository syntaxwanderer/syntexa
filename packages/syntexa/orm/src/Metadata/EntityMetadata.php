<?php

declare(strict_types=1);

namespace Syntexa\Orm\Metadata;

class EntityMetadata
{
    /**
     * @param array<string, ColumnMetadata> $columns
     * @param array<string, RelationshipMetadata> $relationships
     */
    public function __construct(
        public readonly string $className,
        public readonly string $tableName,
        public readonly array $columns,
        public readonly ?ColumnMetadata $identifier,
        public readonly ?string $domainClass = null,
        public readonly ?string $mapperClass = null,
        public readonly ?string $repositoryClass = null,
        public readonly array $relationships = [],
    ) {
    }

    /**
     * Get relationship metadata by property name
     */
    public function getRelationship(string $propertyName): ?RelationshipMetadata
    {
        return $this->relationships[$propertyName] ?? null;
    }

    /**
     * Check if entity has any relationships
     */
    public function hasRelationships(): bool
    {
        return !empty($this->relationships);
    }
}


