<?php

declare(strict_types=1);

namespace Syntexa\Orm\Attributes;

use Attribute;
use Syntexa\Core\Attributes\DocumentedAttributeInterface;
use Syntexa\Core\Attributes\DocumentedAttributeTrait;

/**
 * Marks a property as the entity identifier (primary key)
 * 
 * @see DocumentedAttributeInterface
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Id implements DocumentedAttributeInterface
{
    use DocumentedAttributeTrait;

    public readonly ?string $doc;

    public function __construct(?string $doc = null)
    {
        $this->doc = $doc;
    }

    public function getDocPath(): string
    {
        return $this->doc ?? 'docs/en/attributes/Id.md';
    }
}


