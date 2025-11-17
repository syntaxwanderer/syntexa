<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa response:generate LoginFormResponse
 */
namespace Syntexa\Modules\UserFrontend\Response;

use Syntexa\Core\Attributes\AsResponse;
use Syntexa\UserFrontend\Application\Response\LoginFormResponse as SyntexaLoginFormResponseBase;
use Syntexa\UserFrontend\Application\Response\Traits\LoginFormExperienceTrait as SyntexaLoginFormExperienceTrait;


#[AsResponse(
    of: SyntexaLoginFormResponseBase::class
)]
class LoginFormResponse
{

    use SyntexaLoginFormExperienceTrait;
}
