<?php

declare(strict_types=1);

namespace Syntexa\Orm\Attributes;

use Attribute;

/**
 * Mark a class as an Entity
 * Similar to #[AsRequest] for requests
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AsEntity
{
    public function __construct(
        public ?string $table = null,
        public ?string $schema = null,
    ) {
    }
}

