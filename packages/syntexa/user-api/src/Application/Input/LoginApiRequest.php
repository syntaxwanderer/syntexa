<?php

declare(strict_types=1);

namespace Syntexa\User\Application\Input;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\Core\Contract\RequestInterface;
use Syntexa\User\Application\Input\Traits\LoginApiRequiredFieldsTrait;
use Syntexa\User\Application\Output\Http\LoginApiResponse;

#[AsRequest(
    protocol: 'http',
    responseWith: LoginApiResponse::class,
    path: '/api/login',
    methods: ['POST'],
    name: 'api.login'
)]
class LoginApiRequest implements RequestInterface
{
    use LoginApiRequiredFieldsTrait;
}

