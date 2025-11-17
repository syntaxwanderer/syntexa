<?php

declare(strict_types=1);

namespace Acme\Marketing\Response\Traits;

use Syntexa\Core\Attributes\AsResponsePart;
use Syntexa\User\Application\Response\LoginApiResponse;

#[AsResponsePart(of: LoginApiResponse::class)]
trait LoginApiRewardTrait
{
    public ?string $rewardMessage = null;
}

