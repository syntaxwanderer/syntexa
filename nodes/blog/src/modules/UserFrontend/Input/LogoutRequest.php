<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa request:generate LogoutRequest
 */
namespace Syntexa\Modules\UserFrontend\Input;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\UserFrontend\Application\Input\LogoutRequest as SyntexaLogoutRequestBase;
use Syntexa\Core\Contract\RequestInterface as SyntexaRequestInterface;


#[AsRequest(
    base: SyntexaLogoutRequestBase::class
)]
class LogoutRequest implements SyntexaRequestInterface
{
}
