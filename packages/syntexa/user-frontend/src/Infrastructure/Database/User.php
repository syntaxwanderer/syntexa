<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Infrastructure\Database;

use Syntexa\Orm\Attributes\AsEntity;
use Syntexa\Orm\Attributes\Column;
use Syntexa\Orm\Attributes\GeneratedValue;
use Syntexa\Orm\Attributes\Id;
use Syntexa\Orm\Attributes\TimestampColumn;
use Syntexa\UserFrontend\Domain\Entity\User as DomainUser;

/**
 * Base storage entity for User (module-local)
 * Extensions from other modules are applied via wrapper in src/infrastructure/database/User.php
 */
#[AsEntity(
    table: 'users',
    domainClass: DomainUser::class
)]
class User
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'int')]
    private ?int $id = null;

    #[Column(name: 'email', type: 'string', unique: true)]
    private string $email = '';

    #[Column(name: 'password_hash', type: 'string')]
    private string $passwordHash = '';

    #[Column(name: 'name', type: 'string', nullable: true)]
    private ?string $name = null;

    #[TimestampColumn(type: 'created')]
    #[Column(name: 'created_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[TimestampColumn(type: 'updated')]
    #[Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}

