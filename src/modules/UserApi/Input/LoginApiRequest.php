<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa request:generate LoginApiRequest
 */
namespace Syntexa\Modules\UserApi\Input;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\User\Application\Input\LoginApiRequest as SyntexaLoginApiRequestBase;
use Syntexa\Modules\UserApi\Output\LoginApiResponse as SyntexaLoginApiResponse;
use Acme\Marketing\Application\Input\Traits\LoginMarketingTagTrait as AcmeLoginMarketingTagTrait;
use Syntexa\User\Application\Input\Traits\LoginApiRequiredFieldsTrait as SyntexaLoginApiRequiredFieldsTrait;
use Syntexa\User\Application\Input\Traits\LoginApiTrackingTrait as SyntexaLoginApiTrackingTrait;
use Syntexa\Core\Contract\RequestInterface as SyntexaRequestInterface;


#[AsRequest(
    base: SyntexaLoginApiRequestBase::class,
    responseWith: SyntexaLoginApiResponse::class
)]
class LoginApiRequest implements SyntexaRequestInterface
{

    use AcmeLoginMarketingTagTrait;
    use SyntexaLoginApiRequiredFieldsTrait;
    use SyntexaLoginApiTrackingTrait;
}
