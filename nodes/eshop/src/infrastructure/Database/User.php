<?php

declare(strict_types=1);
namespace Syntexa\Infrastructure\Database;

use Syntexa\Orm\Attributes\AsEntity;
use Syntexa\UserDomain\Infrastructure\Database\User as BaseUser;
use Syntexa\Modules\UserDomain\Domain\User as DomainUserWrapper;
use Acme\Marketing\Infrastructure\Database\UserMarketingProfileTrait as AcmeUserMarketingProfileTraitTrait;

/**
 * Wrapper to apply cross-module extensions (traits) over base storage entity.
 * Base storage lives in module: Syntexa\UserDomain\Infrastructure\Database\User
 */
#[AsEntity(table: 'users', domainClass: DomainUserWrapper::class)]
class User extends BaseUser
{
    use AcmeUserMarketingProfileTraitTrait;
}
