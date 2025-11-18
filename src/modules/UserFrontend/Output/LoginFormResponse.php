<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa response:generate LoginFormResponse
 */
namespace Syntexa\Modules\UserFrontend\Output;

use Syntexa\Core\Attributes\AsResponse;
use Syntexa\UserFrontend\Application\Output\LoginFormResponse as SyntexaLoginFormResponseBase;
use Syntexa\UserFrontend\Application\Output\Traits\LoginFormExperienceTrait as SyntexaLoginFormExperienceTrait;
use Syntexa\Core\Contract\ResponseInterface as SyntexaResponseInterface;


#[AsResponse(
    of: SyntexaLoginFormResponseBase::class
)]
class LoginFormResponse implements SyntexaResponseInterface
{

    use SyntexaLoginFormExperienceTrait;
}
