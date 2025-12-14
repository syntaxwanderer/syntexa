<?php

declare(strict_types=1);

namespace Syntexa\Orm\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToOne
{
    public function __construct(
        public string $targetEntity,
        public ?string $mappedBy = null,
        public ?string $inversedBy = null,
        public string $fetch = 'lazy',
        public array $cascade = [],
        public bool $orphanRemoval = false
    ) {
    }
}

