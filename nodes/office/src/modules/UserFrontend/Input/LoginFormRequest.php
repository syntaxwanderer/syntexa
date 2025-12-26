<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa request:generate LoginFormRequest
 */
namespace Syntexa\Modules\UserFrontend\Input;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\UserFrontend\Application\Input\LoginFormRequest as SyntexaLoginFormRequestBase;
use Syntexa\UserFrontend\Application\Output\LoginFormResponse as SyntexaLoginFormResponse;
use Syntexa\UserFrontend\Application\Input\Traits\LoginFormRequiredFieldsTrait as SyntexaLoginFormRequiredFieldsTrait;
use Syntexa\Core\Contract\RequestInterface as SyntexaRequestInterface;


#[AsRequest(
    path: '/',
    base: SyntexaLoginFormRequestBase::class,
    responseWith: SyntexaLoginFormResponse::class
)]
class LoginFormRequest implements SyntexaRequestInterface
{

    use SyntexaLoginFormRequiredFieldsTrait;
}
