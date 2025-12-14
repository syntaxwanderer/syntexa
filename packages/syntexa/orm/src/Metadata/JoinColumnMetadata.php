<?php

declare(strict_types=1);

namespace Syntexa\Orm\Metadata;

/**
 * Metadata for join columns in relationships
 */
class JoinColumnMetadata
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $referencedColumnName = null,
        public readonly bool $nullable = true,
    ) {
    }
}

