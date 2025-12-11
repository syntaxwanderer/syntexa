<?php

declare(strict_types=1);

namespace Syntexa\Orm\Attributes;

use Attribute;
use Syntexa\Core\Attributes\DocumentedAttributeInterface;
use Syntexa\Core\Attributes\DocumentedAttributeTrait;

/**
 * Mark a class as an Entity
 * Similar to #[AsRequest] for requests
 * 
 * @see DocumentedAttributeInterface
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AsEntity implements DocumentedAttributeInterface
{
    use DocumentedAttributeTrait;

    public readonly ?string $doc;

    public function __construct(
        ?string $doc = null,
        public ?string $table = null,
        public ?string $schema = null,
        public ?string $domainClass = null,
        public ?string $mapper = null,
        public ?string $repositoryClass = null,
    ) {
        $this->doc = $doc;
    }

    public function getDocPath(): string
    {
        return $this->doc ?? 'docs/en/attributes/AsEntity.md';
    }
}

