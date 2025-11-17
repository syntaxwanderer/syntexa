<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa request:generate LoginApiRequest
 */
namespace Syntexa\Modules\UserApi\Request;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\User\Application\Request\LoginApiRequest as SyntexaLoginApiRequestBase;
use Acme\Marketing\Request\Traits\LoginMarketingTagTrait as AcmeLoginMarketingTagTrait;
use Syntexa\User\Application\Request\Traits\LoginApiRequiredFieldsTrait as SyntexaLoginApiRequiredFieldsTrait;
use Syntexa\User\Application\Request\Traits\LoginApiTrackingTrait as SyntexaLoginApiTrackingTrait;


#[AsRequest(
    of: SyntexaLoginApiRequestBase::class
)]
class LoginApiRequest
{

    use AcmeLoginMarketingTagTrait;
    use SyntexaLoginApiRequiredFieldsTrait;
    use SyntexaLoginApiTrackingTrait;
}
