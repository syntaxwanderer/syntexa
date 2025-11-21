<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Application\Input\Http;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\Core\Contract\RequestInterface;
use Syntexa\UserFrontend\Application\Output\DashboardResponse;

#[AsRequest(
    responseWith: DashboardResponse::class,
    path: '/dashboard',
    methods: ['GET'],
    name: 'dashboard'
)]
class DashboardRequest implements RequestInterface
{
}

