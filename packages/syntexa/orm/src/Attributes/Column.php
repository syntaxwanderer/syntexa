<?php

declare(strict_types=1);

namespace Syntexa\Orm\Attributes;

use Attribute;
use Syntexa\Core\Attributes\DocumentedAttributeInterface;
use Syntexa\Core\Attributes\DocumentedAttributeTrait;

/**
 * Marks a property as a database column
 * 
 * @see DocumentedAttributeInterface
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Column implements DocumentedAttributeInterface
{
    use DocumentedAttributeTrait;

    public readonly ?string $doc;

    public function __construct(
        ?string $doc = null,
        public ?string $name = null,
        public string $type = 'string',
        public bool $nullable = false,
        public bool $unique = false,
        public ?int $length = null,
        public mixed $default = null,
    ) {
        $this->doc = $doc;
    }

    public function getDocPath(): string
    {
        return $this->doc ?? 'docs/en/attributes/Column.md';
    }
}


