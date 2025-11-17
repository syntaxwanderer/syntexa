<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa request:generate LoginFormRequest
 */
namespace Syntexa\Modules\UserFrontend\Request;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\UserFrontend\Application\Request\LoginFormRequest as SyntexaLoginFormRequestBase;
use Syntexa\Modules\UserFrontend\Response\LoginFormResponse as SyntexaLoginFormResponse;
use Syntexa\Core\Contract\RequestInterface as SyntexaRequestInterface;


#[AsRequest(
    of: SyntexaLoginFormRequestBase::class,
    responseWith: SyntexaLoginFormResponse::class
)]
class LoginFormRequest implements SyntexaRequestInterface
{
}
