<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Application\Input;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\Core\Contract\RequestInterface;
use Syntexa\UserFrontend\Application\Input\Traits\LoginFormRequiredFieldsTrait;
use Syntexa\UserFrontend\Application\Output\LoginFormResponse;

#[AsRequest(
    protocol: 'http',
    path: '/login',
    methods: ['GET', 'POST'],
    name: 'login.form',
    responseWith: LoginFormResponse::class
)]
class LoginFormRequest implements RequestInterface
{
    use LoginFormRequiredFieldsTrait;
}

