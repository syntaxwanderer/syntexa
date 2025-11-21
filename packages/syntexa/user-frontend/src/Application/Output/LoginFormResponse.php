<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Application\Output;

use Syntexa\Core\Http\Response\GenericResponse;
use Syntexa\Core\Attributes\AsResponse;
use Syntexa\Core\Http\Response\ResponseFormat;

#[AsResponse(handle: 'auth.login', format: ResponseFormat::Layout)]
class LoginFormResponse extends GenericResponse
{
}

