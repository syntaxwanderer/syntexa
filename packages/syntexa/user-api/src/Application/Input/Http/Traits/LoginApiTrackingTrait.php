<?php

declare(strict_types=1);

namespace Syntexa\User\Application\Input\Http\Traits;

use Syntexa\Core\Attributes\AsRequestPart;
use Syntexa\User\Application\Input\Http\LoginApiRequest;

#[AsRequestPart(of: LoginApiRequest::class)]
trait LoginApiTrackingTrait
{
    public ?string $utmSource = null;
    public ?string $utmMedium = null;
}

