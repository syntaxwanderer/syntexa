<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa response:generate LoginApiResponse
 */
namespace Syntexa\Modules\UserApi\Output;

use Acme\Marketing\Output\Traits\LoginApiRewardTrait as AcmeLoginApiRewardTrait;
use Syntexa\Core\Attributes\AsResponse;
use Syntexa\Core\Contract\ResponseInterface as SyntexaResponseInterface;
use Syntexa\User\Application\Output\Http\LoginApiResponse as SyntexaLoginApiResponseBase;


#[AsResponse(
    of: SyntexaLoginApiResponseBase::class
)]
class LoginApiResponse implements SyntexaResponseInterface
{

    use AcmeLoginApiRewardTrait;
}
