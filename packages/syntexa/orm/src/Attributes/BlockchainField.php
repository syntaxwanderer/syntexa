<?php

declare(strict_types=1);

namespace Syntexa\Orm\Attributes;

use Attribute;
use Syntexa\Core\Attributes\DocumentedAttributeInterface;
use Syntexa\Core\Attributes\DocumentedAttributeTrait;

/**
 * Marks a property for blockchain logging
 * 
 * Fields marked with this attribute will be included in blockchain transactions.
 * Only fields explicitly marked will be included (opt-in approach).
 * 
 * @see DocumentedAttributeInterface
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class BlockchainField implements DocumentedAttributeInterface
{
    use DocumentedAttributeTrait;

    public readonly ?string $doc;

    public function __construct(
        ?string $doc = null,
        public bool $encrypt = false,  // Opt-in encryption for privacy
        public ?string $encryptionKeyId = null,  // Optional: specific encryption key
        public bool $includeRelationships = true,  // Include FK + related entity hashes
    ) {
        $this->doc = $doc;
    }

    public function getDocPath(): string
    {
        return $this->doc ?? 'docs/en/attributes/BlockchainField.md';
    }
}

