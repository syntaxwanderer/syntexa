<?php

declare(strict_types=1);

namespace Syntexa\Core\Attributes;

use Attribute;

/**
 * Marks a trait (or helper class) as an extension part of a request DTO.
 *
 * Modules can provide request parts that will be combined into the final
 * project-specific request class during code generation.
 * 
 * @see DocumentedAttributeInterface
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AsRequestPart implements DocumentedAttributeInterface
{
    use DocumentedAttributeTrait;

    public readonly ?string $doc;

    public function __construct(
        /**
         * Fully-qualified class name of the base request that this part targets.
         */
        public string $base,
        ?string $doc = null
    ) {
        $this->doc = $doc;
    }

    public function getDocPath(): string
    {
        return $this->doc ?? 'docs/en/attributes/AsRequestPart.md';
    }
}

