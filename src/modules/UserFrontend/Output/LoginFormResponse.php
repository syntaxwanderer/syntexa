<?php

declare(strict_types=1);
namespace Syntexa\Modules\UserFrontend\Output;

use Syntexa\Core\Attributes\AsResponse;
use Syntexa\UserFrontend\Application\Output\LoginFormResponse as SyntexaLoginFormResponseBase;
use Syntexa\UserFrontend\Application\Output\Traits\LoginFormExperienceTrait as SyntexaLoginFormExperienceTrait;

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa response:generate LoginFormResponse
 */

#[AsResponse(
    base: SyntexaLoginFormResponseBase::class
)]
class LoginFormResponse extends SyntexaLoginFormResponseBase
{

    use SyntexaLoginFormExperienceTrait;
}
