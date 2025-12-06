<?php

declare(strict_types=1);

namespace Syntexa\Orm\Attributes;

use Attribute;
use Syntexa\Core\Attributes\DocumentedAttributeInterface;
use Syntexa\Core\Attributes\DocumentedAttributeTrait;

/**
 * Mark a trait as extending an Entity
 * Similar to #[AsRequestPart] for requests
 * 
 * Usage:
 * #[AsEntityPart(doc: 'docs/attributes/AsEntityPart.md', base: User::class)]
 * trait UserMarketingTrait {
 *     public ?string $marketingTag;
 * }
 * 
 * @see DocumentedAttributeInterface
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AsEntityPart implements DocumentedAttributeInterface
{
    use DocumentedAttributeTrait;

    public readonly ?string $doc;

    public function __construct(
        ?string $doc = null,
        public string $base,
    ) {
        $this->doc = $doc;
    }

    public function getDocPath(): string
    {
        return $this->doc ?? 'docs/en/attributes/AsEntityPart.md';
    }
}

