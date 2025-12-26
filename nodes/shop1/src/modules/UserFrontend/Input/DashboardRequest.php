<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa request:generate DashboardRequest
 */
namespace Syntexa\Modules\UserFrontend\Input;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\UserFrontend\Application\Input\DashboardRequest as SyntexaDashboardRequestBase;
use Syntexa\UserFrontend\Application\Output\DashboardResponse as SyntexaDashboardResponse;
use Syntexa\Core\Contract\RequestInterface as SyntexaRequestInterface;


#[AsRequest(
    base: SyntexaDashboardRequestBase::class,
    responseWith: SyntexaDashboardResponse::class
)]
class DashboardRequest implements SyntexaRequestInterface
{
}
