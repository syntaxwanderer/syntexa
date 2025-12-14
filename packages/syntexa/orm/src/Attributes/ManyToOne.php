<?php

declare(strict_types=1);

namespace Syntexa\Orm\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToOne
{
    public function __construct(
        public string $targetEntity,
        public ?string $inversedBy = null,
        public string $fetch = 'lazy',
        public array $cascade = []
    ) {
    }
}

