<?php

declare(strict_types=1);

namespace Syntexa\Core\Attributes;

use Syntexa\Core\Http\Response\ResponseFormat;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class AsResponse
{
    public function __construct(
        public ?string $of = null,
        public ?string $handle = null,
        public ?array $context = null,
        public ?ResponseFormat $format = null,
        public ?string $renderer = null,
    ) {}
}


