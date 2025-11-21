<?php

declare(strict_types=1);

namespace Syntexa\Core\Attributes;

use Attribute;

/**
 * Marks a trait as an extension part of a response DTO.
 *
 * Modules can provide response parts that will be combined into the final
 * project-specific response class during code generation.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AsResponsePart
{
    public function __construct(
        /**
         * Fully-qualified class name of the base response that this part targets.
         */
        public string $base
    ) {
    }
}

