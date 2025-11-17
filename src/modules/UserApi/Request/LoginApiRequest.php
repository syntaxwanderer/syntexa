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
use Syntexa\User\Application\Request\Traits\LoginApiRequiredFieldsTrait as SyntexaLoginApiRequiredFieldsTrait;
use Syntexa\User\Application\Request\Traits\LoginApiTrackingTrait as SyntexaLoginApiTrackingTrait;
use Syntexa\Core\Contract\RequestInterface as SyntexaRequestInterface;


#[AsRequest(
    of: SyntexaLoginApiRequestBase::class,
    responseWith: SyntexaLoginApiResponse::class
)]
class LoginApiRequest implements SyntexaRequestInterface
{

    use AcmeLoginMarketingTagTrait;
    use SyntexaLoginApiRequiredFieldsTrait;
    use SyntexaLoginApiTrackingTrait;
}
