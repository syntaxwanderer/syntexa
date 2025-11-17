<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa response:generate LoginFormResponse
 */
namespace Syntexa\Modules\UserFrontend\Response;

use Syntexa\Core\Attributes\AsResponse;

#[AsResponse(
    handle: 'login',
    format: \Syntexa\Core\Http\Response\ResponseFormat::Layout
)]
class LoginFormResponse extends \Syntexa\UserFrontend\Application\Response\LoginFormResponse
{

    use \Syntexa\UserFrontend\Application\Response\Traits\LoginFormExperienceTrait;
}
