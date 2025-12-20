<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Application\Input;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\Core\Contract\RequestInterface;
use Syntexa\UserFrontend\Application\Output\DashboardResponse;

#[AsRequest(
    responseWith: DashboardResponse::class,
    path: '/dashboard',
    methods: ['GET'],
    name: 'dashboard',
    protocol: 'http'
)]
class DashboardRequest implements RequestInterface
{
}

