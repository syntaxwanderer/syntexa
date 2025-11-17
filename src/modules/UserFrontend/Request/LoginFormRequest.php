<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa request:generate LoginFormRequest
 */
namespace Syntexa\Modules\UserFrontend\Request;

use Syntexa\Core\Attributes\AsRequest;

#[AsRequest(
    path: '/login',
    methods: ['GET'],
    name: 'login.form',
    responseWith: \Syntexa\Modules\UserFrontend\Response\LoginFormResponse::class
)]
class LoginFormRequest extends \Syntexa\UserFrontend\Application\Request\LoginFormRequest
{
}
