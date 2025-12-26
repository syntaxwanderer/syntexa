<?php

declare(strict_types=1);
namespace Syntexa\Modules\UserDomain\Domain;

use Syntexa\UserDomain\Domain\Entity\User as SyntexaUserBase;
use Acme\Marketing\Domain\Entity\UserMarketingProfileDomainTrait as AcmeUserMarketingProfileDomainTraitTrait;

/**
 * Domain wrapper to apply cross-module domain extensions.
 * Base domain lives in module: Syntexa\UserDomain\Domain\Entity\User
 */
class User extends SyntexaUserBase
{

    use AcmeUserMarketingProfileDomainTraitTrait;
}
