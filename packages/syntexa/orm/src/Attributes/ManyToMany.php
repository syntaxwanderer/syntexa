<?php

declare(strict_types=1);

namespace Syntexa\Orm\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToMany
{
    public function __construct(
        public string $targetEntity,
        public ?string $joinTable = null,
        public ?string $joinColumn = null,
        public ?string $inverseJoinColumn = null,
        public string $fetch = 'lazy',
        public array $cascade = []
    ) {
    }
}

