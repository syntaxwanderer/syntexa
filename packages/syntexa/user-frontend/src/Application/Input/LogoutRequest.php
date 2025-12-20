<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Application\Input;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\Core\Contract\RequestInterface;

#[AsRequest(
    protocol: 'http',
    path: '/logout',
    methods: ['GET', 'POST'],
    name: 'logout'
)]
class LogoutRequest implements RequestInterface
{
}

