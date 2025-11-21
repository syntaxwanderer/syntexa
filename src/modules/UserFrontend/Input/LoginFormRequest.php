<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa request:generate LoginFormRequest
 */
namespace Syntexa\Modules\UserFrontend\Input;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\UserFrontend\Application\Input\Http\LoginFormRequest as SyntexaLoginFormRequestBase;
use Syntexa\Modules\UserFrontend\Output\LoginFormResponse as SyntexaLoginFormResponse;
use Syntexa\UserFrontend\Application\Input\Http\Traits\LoginFormRequiredFieldsTrait as SyntexaLoginFormRequiredFieldsTrait;


#[AsRequest(
    base: SyntexaLoginFormRequestBase::class,
    responseWith: SyntexaLoginFormResponse::class
)]
class LoginFormRequest extends SyntexaLoginFormRequestBase
{

    use SyntexaLoginFormRequiredFieldsTrait;
}
