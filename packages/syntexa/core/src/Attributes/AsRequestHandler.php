<?php

declare(strict_types=1);

namespace Syntexa\Core\Attributes;

use Attribute;
use Syntexa\Core\Queue\HandlerExecution;

/**
 * Marks a class as an HTTP request handler
 * 
 * @see DocumentedAttributeInterface
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AsRequestHandler implements DocumentedAttributeInterface
{
    use DocumentedAttributeTrait;

    public readonly ?string $doc;

    public function __construct(
        public string $for,
        public HandlerExecution|string|null $execution = null,
        public ?string $transport = null,
        public ?string $queue = null,
        public ?int $priority = null,
        ?string $doc = null,
    ) {
        $this->doc = $doc;
    }

    public function getDocPath(): string
    {
        return $this->doc ?? 'docs/en/attributes/AsRequestHandler.md';
    }
}