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
use Syntexa\Core\Http\Response\GenericResponse as SyntexaGenericResponseBase;
use Syntexa\Core\Contract\ResponseInterface as SyntexaResponseInterface;


#[AsResponse(
    of: SyntexaLoginApiResponseBase::class,
    handle: 'api.login',
    format: \Syntexa\Core\Http\Response\ResponseFormat::Json
)]
class LoginApiResponse extends SyntexaGenericResponseBase implements SyntexaResponseInterface
{

    use AcmeLoginApiRewardTrait;
}
