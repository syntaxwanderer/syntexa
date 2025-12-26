<?php

declare(strict_types=1);

namespace Syntexa\Core\Attributes;

use Attribute;

/**
 * Marks a class as a module configuration
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AsModule implements DocumentedAttributeInterface
{
    use DocumentedAttributeTrait;

    public readonly ?string $doc;

    public function __construct(
        public string $name,
        public bool $active = true,
        public string $role = 'observer', // 'validator' or 'observer'
        ?string $doc = null,
    ) {
        $this->doc = $doc;
    }

    public function getDocPath(): string
    {
        return $this->doc ?? 'docs/en/attributes/AsModule.md';
    }
}
