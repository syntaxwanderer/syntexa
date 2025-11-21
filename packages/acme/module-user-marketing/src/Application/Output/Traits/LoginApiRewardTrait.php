<?php

declare(strict_types=1);

namespace Acme\Marketing\Application\Output\Traits;

use Syntexa\Core\Attributes\AsResponsePart;
use Syntexa\User\Application\Output\Http\LoginApiResponse;

#[AsResponsePart(base: LoginApiResponse::class)]
trait LoginApiRewardTrait
{
    public ?string $rewardMessage = null;
}

