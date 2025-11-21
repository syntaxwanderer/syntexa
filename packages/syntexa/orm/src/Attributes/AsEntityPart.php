<?php

declare(strict_types=1);

namespace Syntexa\Orm\Attributes;

use Attribute;

/**
 * Mark a trait as extending an Entity
 * Similar to #[AsRequestPart] for requests
 * 
 * Usage:
 * #[AsEntityPart(base: User::class)]
 * trait UserMarketingTrait {
 *     public ?string $marketingTag;
 * }
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_TRAIT)]
class AsEntityPart
{
    public function __construct(
        public string $base,
    ) {
    }
}

