<?php

declare(strict_types=1);

namespace Syntexa\Orm\Attributes;

use Attribute;
use Syntexa\Core\Attributes\DocumentedAttributeInterface;
use Syntexa\Core\Attributes\DocumentedAttributeTrait;

/**
 * Marks a property as a timestamp column (created_at or updated_at)
 * 
 * @see DocumentedAttributeInterface
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class TimestampColumn implements DocumentedAttributeInterface
{
    use DocumentedAttributeTrait;

    public readonly ?string $doc;

    public const TYPE_CREATED = 'created';
    public const TYPE_UPDATED = 'updated';

    public function __construct(
        public string $type,
        ?string $doc = null,
    ) {
        $this->doc = $doc;
        if (!in_array($type, [self::TYPE_CREATED, self::TYPE_UPDATED], true)) {
            throw new \InvalidArgumentException('Invalid timestamp column type: ' . $type);
        }
    }

    public function getDocPath(): string
    {
        return $this->doc ?? 'docs/en/attributes/TimestampColumn.md';
    }
}


