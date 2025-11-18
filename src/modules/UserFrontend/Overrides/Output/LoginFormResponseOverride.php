<?php

declare(strict_types=1);

namespace Syntexa\Modules\UserFrontend\Overrides\Output;

use Syntexa\Core\Attributes\AsResponseOverride;
use Syntexa\Core\Http\Response\ResponseFormat;
use Syntexa\UserFrontend\Application\Output\LoginFormResponse;

#[AsResponseOverride(
    of: LoginFormResponse::class,
    handle: 'auth.login',
    format: ResponseFormat::Layout,
    context: ['title' => 'Sign in'],
    priority: 100
)]
class LoginFormResponseOverride
{
}


