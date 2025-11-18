<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa request:generate LoginApiRequest
 */
namespace Syntexa\Modules\UserApi\Input;

use Acme\Marketing\Input\Traits\LoginMarketingTagTrait as AcmeLoginMarketingTagTrait;
use Syntexa\Core\Attributes\AsRequest;
use Syntexa\Core\Contract\RequestInterface as SyntexaRequestInterface;
use Syntexa\Modules\UserApi\Output\LoginApiResponse as SyntexaLoginApiResponse;
use Syntexa\User\Application\Input\Http\LoginApiRequest as SyntexaLoginApiRequestBase;
use Syntexa\User\Application\Input\Http\Traits\LoginApiRequiredFieldsTrait as SyntexaLoginApiRequiredFieldsTrait;
use Syntexa\User\Application\Input\Http\Traits\LoginApiTrackingTrait as SyntexaLoginApiTrackingTrait;


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
