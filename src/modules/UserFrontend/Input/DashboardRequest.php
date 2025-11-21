<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa request:generate DashboardRequest
 */
namespace Syntexa\Modules\UserFrontend\Input;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\UserFrontend\Application\Input\Http\DashboardRequest as SyntexaDashboardRequestBase;
use Syntexa\Modules\UserFrontend\Output\DashboardResponse as SyntexaDashboardResponse;


#[AsRequest(
    base: SyntexaDashboardRequestBase::class,
    responseWith: SyntexaDashboardResponse::class
)]
class DashboardRequest extends SyntexaDashboardRequestBase
{
}
