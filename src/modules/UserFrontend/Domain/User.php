<?php

declare(strict_types=1);

namespace Syntexa\Modules\UserFrontend\Domain;

use Syntexa\UserFrontend\Domain\Entity\User as BaseUser;
use Acme\Marketing\Domain\Entity\UserMarketingProfileDomainTrait as AcmeUserMarketingProfileDomainTraitTrait;

/**
 * Domain wrapper to apply cross-module domain extensions.
 * Base domain lives in module: Syntexa\UserFrontend\Domain\Entity\User
 */
class User extends BaseUser
{
    use AcmeUserMarketingProfileDomainTraitTrait;
}


