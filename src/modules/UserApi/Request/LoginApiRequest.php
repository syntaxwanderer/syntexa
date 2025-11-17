<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa request:generate LoginApiRequest
 */
namespace Syntexa\Modules\UserApi\Request;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\User\Application\Request\LoginApiRequest as SyntexaLoginApiRequestBase;
use Syntexa\Modules\UserApi\Response\LoginApiResponse as SyntexaLoginApiResponse;
use Acme\Marketing\Request\Traits\LoginMarketingTagTrait as AcmeLoginMarketingTagTrait;
use Syntexa\User\Application\Request\Traits\LoginApiTrackingTrait as SyntexaLoginApiTrackingTrait;


#[AsRequest(
    path: '/api/login',
    methods: ['POST'],
    name: 'api.login',
    responseWith: SyntexaLoginApiResponse::class
)]
class LoginApiRequest extends SyntexaLoginApiRequestBase
{

    use AcmeLoginMarketingTagTrait;
    use SyntexaLoginApiTrackingTrait;
}
