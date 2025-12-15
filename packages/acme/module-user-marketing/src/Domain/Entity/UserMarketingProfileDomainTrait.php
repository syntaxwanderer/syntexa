<?php

declare(strict_types=1);

namespace Acme\Marketing\Domain\Entity;

use Syntexa\Orm\Attributes\AsDomainPart;
use Syntexa\UserFrontend\Domain\Entity\User;

#[AsDomainPart(base: User::class)]
trait UserMarketingProfileDomainTrait
{
    private ?\DateTimeImmutable $birthday = null;
    private ?\DateTimeImmutable $lastStoreVisitAt = null;
    private bool $marketingOptIn = false;
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

    public function getMarketingOptIn(): bool
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

