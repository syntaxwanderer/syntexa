<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa response:generate LoginApiResponse
 */
namespace Syntexa\Modules\UserApi\Response;

use Syntexa\Core\Attributes\AsResponse;
use Syntexa\User\Application\Response\LoginApiResponse as SyntexaLoginApiResponseBase;
use Acme\Marketing\Response\Traits\LoginApiRewardTrait as AcmeLoginApiRewardTrait;
use Syntexa\Core\Contract\ResponseInterface as SyntexaResponseInterface;


#[AsResponse(
    of: SyntexaLoginApiResponseBase::class
)]
class LoginApiResponse implements SyntexaResponseInterface
{

    use AcmeLoginApiRewardTrait;
}
