<?php

declare(strict_types=1);

namespace Syntexa\Orm\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class JoinColumn
{
    public function __construct(
        public ?string $name = null,
        public ?string $referencedColumnName = null,
        public bool $nullable = true
    ) {
    }
}

