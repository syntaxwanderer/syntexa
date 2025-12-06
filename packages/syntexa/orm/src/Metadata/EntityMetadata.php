<?php

declare(strict_types=1);

namespace Syntexa\Orm\Metadata;

class EntityMetadata
{
    /**
     * @param array<string, ColumnMetadata> $columns
     */
    public function __construct(
        public readonly string $className,
        public readonly string $tableName,
        public readonly array $columns,
        public readonly ?ColumnMetadata $identifier,
    ) {
    }
}


