<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Domain\Entity;

use Syntexa\Orm\Attributes\AsEntity;
use Syntexa\Orm\Attributes\Column;
use Syntexa\Orm\Entity\BaseEntity;
use Syntexa\Orm\Entity\Traits\TimestampedEntityTrait;

/**
 * User domain entity
 * 
 * Usage:
 * 1. Generate wrapper: bin/syntexa entity:generate User
 * 2. Use EntityManager to persist/retrieve
 */
#[AsEntity(table: 'users')]
class User extends BaseEntity
{
    use TimestampedEntityTrait;

    #[Column(name: 'email', type: 'string', unique: true)]
    private string $email = '';

    #[Column(name: 'password_hash', type: 'string')]
    private string $passwordHash = '';

    #[Column(name: 'name', type: 'string', nullable: true)]
    private ?string $name = null;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    /**
     * Hash password
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Set password (hashes it)
     */
    public function setPassword(string $password): void
    {
        $this->passwordHash = self::hashPassword($password);
    }

    /**
     * Get password hash (for storage)
     */
    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    // Custom toArray/fromArray no longer required thanks to column attributes.
}
