<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Application\Output;

use Syntexa\Core\Attributes\AsResponse;
use Syntexa\Core\Http\Response\ResponseFormat;
use Syntexa\Core\Http\Response\GenericResponse;

#[AsResponse(handle: 'dashboard', format: ResponseFormat::Layout)]
class DashboardResponse extends GenericResponse
{
}

