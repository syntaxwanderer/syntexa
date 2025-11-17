<?php

declare(strict_types=1);

namespace Syntexa\User\Application\Request\Traits;

use Syntexa\Core\Attributes\AsRequestPart;
use Syntexa\User\Application\Request\LoginApiRequest;

#[AsRequestPart(of: LoginApiRequest::class)]
trait LoginApiRequiredFieldsTrait
{
    public int $id;
}

