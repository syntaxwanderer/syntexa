<?php

declare(strict_types=1);

namespace Syntexa\Core\Attributes;

use Syntexa\Core\Http\Response\ResponseFormat;
use Attribute;

/**
 * Marks a class as a response DTO
 * 
 * @see DocumentedAttributeInterface
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AsResponse implements DocumentedAttributeInterface
{
    use DocumentedAttributeTrait;

    public readonly ?string $doc;

    public function __construct(
        ?string $doc = null,
        public ?string $base = null,
        public ?string $handle = null,
        public ?array $context = null,
        public ?ResponseFormat $format = null,
        public ?string $renderer = null,
    ) {
        $this->doc = $doc;
    }

    public function getDocPath(): string
    {
        return $this->doc ?? 'docs/en/attributes/AsResponse.md';
    }
}


