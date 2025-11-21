<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa response:generate LoginApiResponse
 */
namespace Syntexa\Modules\UserApi\Output;

use Syntexa\Core\Attributes\AsResponse;
use Syntexa\User\Application\Output\Http\LoginApiResponse as SyntexaLoginApiResponseBase;
use Acme\Marketing\Application\Output\Traits\LoginApiRewardTrait as AcmeLoginApiRewardTrait;
use Syntexa\Core\Contract\ResponseInterface as SyntexaResponseInterface;


#[AsResponse(
    base: SyntexaLoginApiResponseBase::class
)]
class LoginApiResponse implements SyntexaResponseInterface
{

    use AcmeLoginApiRewardTrait;
}
