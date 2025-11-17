<?php

declare(strict_types=1);

namespace Acme\Marketing\Request\Traits;

use Syntexa\Core\Attributes\AsRequestPart;
use Syntexa\User\Application\Request\LoginApiRequest;

#[AsRequestPart(of: LoginApiRequest::class)]
trait LoginMarketingTagTrait
{
    public ?string $marketingTag = null;
}

