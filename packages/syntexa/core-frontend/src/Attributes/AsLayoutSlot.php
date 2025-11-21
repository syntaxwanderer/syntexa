<?php

declare(strict_types=1);

namespace Syntexa\Frontend\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AsLayoutSlot
{
    public function __construct(
        public string $handle,
        public string $slot,
        public string $template,
        public array $context = [],
        public int $priority = 0,
    ) {
    }
}

