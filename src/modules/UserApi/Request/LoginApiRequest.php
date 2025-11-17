<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa request:generate LoginApiRequest
 */
namespace Syntexa\Modules\UserApi\Request;

use Syntexa\Core\Attributes\AsRequest;

#[AsRequest(
    path: '/api/login',
    methods: ['POST'],
    name: 'api.login',
    responseWith: \Syntexa\Modules\UserApi\Response\LoginApiResponse::class
)]
class LoginApiRequest extends \Syntexa\User\Application\Request\LoginApiRequest
{

    use \Syntexa\User\Application\Request\Traits\LoginApiTrackingTrait;
    use \Acme\Marketing\Request\Traits\LoginMarketingTagTrait;
}
