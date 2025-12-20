<?php

declare(strict_types=1);

namespace Acme\Marketing\Application\Input\Traits;

use Syntexa\Core\Attributes\AsRequestPart;
use Syntexa\User\Application\Input\LoginApiRequest;

#[AsRequestPart(base: LoginApiRequest::class)]
trait LoginMarketingTagTrait
{
    public ?string $marketingTag = null;
}

