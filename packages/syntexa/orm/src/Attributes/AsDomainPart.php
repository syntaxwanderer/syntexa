<?php

declare(strict_types=1);

namespace Syntexa\Orm\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_TRAIT)]
class AsDomainPart
{
    public function __construct(
        public string $base,
    ) {
    }
}

