<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Application\Response\Traits;

use Syntexa\Core\Attributes\AsResponsePart;
use Syntexa\UserFrontend\Application\Response\LoginFormResponse;

#[AsResponsePart(of: LoginFormResponse::class)]
trait LoginFormExperienceTrait
{
    public ?string $promoHeadline = null;
    public ?string $promoSubtext = null;
}

