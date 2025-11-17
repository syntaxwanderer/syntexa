<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa response:generate LoginApiResponse
 */
namespace Syntexa\Modules\UserApi\Response;

use Syntexa\Core\Attributes\AsResponse;

#[AsResponse(
    handle: 'api.login',
    format: \Syntexa\Core\Http\Response\ResponseFormat::Json
)]
class LoginApiResponse extends \Syntexa\User\Application\Response\LoginApiResponse
{

    use \Acme\Marketing\Response\Traits\LoginApiRewardTrait;
}
