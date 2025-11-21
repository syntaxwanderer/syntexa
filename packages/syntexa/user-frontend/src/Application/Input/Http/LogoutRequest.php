<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Application\Input\Http;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\Core\Contract\RequestInterface;

#[AsRequest(
    path: '/logout',
    methods: ['GET', 'POST'],
    name: 'logout'
)]
class LogoutRequest implements RequestInterface
{
}

