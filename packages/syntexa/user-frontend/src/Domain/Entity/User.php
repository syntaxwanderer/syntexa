<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Domain\Entity;

/**
 * User domain entity
 */
class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        private string $passwordHash,
        public readonly string $name = '',
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
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
     * Convert to array for storage
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'password_hash' => $this->passwordHash,
            'name' => $this->name,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            email: $data['email'],
            passwordHash: $data['password_hash'],
            name: $data['name'] ?? '',
            createdAt: isset($data['created_at']) 
                ? new \DateTimeImmutable($data['created_at']) 
                : new \DateTimeImmutable(),
        );
    }
}

