<?php

namespace Syntexa\Core\Attributes;

use Attribute;
use Syntexa\Core\Queue\HandlerExecution;

#[Attribute(Attribute::TARGET_CLASS)]
class AsRequestHandler
{
    public function __construct(
        public string $for,
        public HandlerExecution|string|null $execution = null,
        public ?string $transport = null,
        public ?string $queue = null,
        public ?int $priority = null,
    ) {
    }
}