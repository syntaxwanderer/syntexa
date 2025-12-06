<?php

declare(strict_types=1);

namespace Acme\Marketing\Domain\Entity;

use Syntexa\Orm\Attributes\AsEntityPart;
use Syntexa\Orm\Attributes\Column;
use Syntexa\UserFrontend\Domain\Entity\User;

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

    public function getBirthday(): ?\DateTimeImmutable
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTimeImmutable $birthday): void
    {
        $this->birthday = $birthday;
    }

    public function getLastStoreVisitAt(): ?\DateTimeImmutable
    {
        return $this->lastStoreVisitAt;
    }

    public function setLastStoreVisitAt(?\DateTimeImmutable $lastStoreVisitAt): void
    {
        $this->lastStoreVisitAt = $lastStoreVisitAt;
    }

    public function hasMarketingOptIn(): bool
    {
        return $this->marketingOptIn;
    }

    public function setMarketingOptIn(bool $marketingOptIn): void
    {
        $this->marketingOptIn = $marketingOptIn;
    }

    public function getFavoriteCategory(): ?string
    {
        return $this->favoriteCategory;
    }

    public function setFavoriteCategory(?string $favoriteCategory): void
    {
        $this->favoriteCategory = $favoriteCategory;
    }
}


