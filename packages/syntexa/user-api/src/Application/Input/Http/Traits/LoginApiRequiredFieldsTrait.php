<?php

declare(strict_types=1);

namespace Syntexa\User\Application\Input\Http\Traits;

use Syntexa\Core\Attributes\AsRequestPart;
use Syntexa\User\Application\Input\Http\LoginApiRequest;

#[AsRequestPart(base: LoginApiRequest::class)]
trait LoginApiRequiredFieldsTrait
{
    public int $id;
}

