<?php

declare(strict_types=1);

namespace Syntexa\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class AsCommand
{
    /**
     * @param string $name Console identifier (e.g. "user:sync")
     * @param string|null $description Short human-readable summary
     * @param array<int, string> $aliases Alternative names that can trigger the command
     * @param array<int, array<string, mixed>> $arguments Positional arguments metadata
     * @param array<int, array<string, mixed>> $options Named options/flags metadata
     * @param string|null $base Optional base class for attribute inheritance (mirrors request/response)
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public array $aliases = [],
        public array $arguments = [],
        public array $options = [],
        public ?string $base = null,
    ) {
    }
}


