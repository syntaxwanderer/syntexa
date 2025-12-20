<?php

declare(strict_types=1);

namespace Acme\Marketing\Infrastructure\Database;

use Syntexa\Orm\Attributes\AsEntityPart;
use Syntexa\Orm\Attributes\Column;
use Syntexa\UserDomain\Infrastructure\Database\User;

#[AsEntityPart(base: User::class)]
trait UserMarketingProfileTrait
{
    #[Column(name: 'birthday', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $birthday = null;

    #[Column(name: 'last_store_visit_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastStoreVisitAt = null;

    #[Column(name: 'marketing_opt_in', type: 'bool', nullable: false, default: false)]
    private bool $marketingOptIn = false;

    #[Column(name: 'favorite_category', type: 'string', nullable: true, length: 64)]
    private ?string $favoriteCategory = null;
}

