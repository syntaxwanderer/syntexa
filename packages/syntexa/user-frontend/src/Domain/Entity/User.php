<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Domain\Entity;

use Syntexa\Orm\Entity\BaseEntity;
use Syntexa\Orm\Attributes\AsEntity;

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
    public string $email = '';
    private string $passwordHash = '';
    public string $name = '';

    // No constructor needed - BaseEntity properties are public and nullable

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

    /**
     * Convert to array for storage
     * Override to handle password_hash field name mapping
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        
        // Map passwordHash to password_hash for database
        if (isset($data['passwordHash'])) {
            $data['password_hash'] = $data['passwordHash'];
            unset($data['passwordHash']);
        }
        
        // BaseEntity already handles DateTimeImmutable -> string conversion
        // But we need to map camelCase to snake_case for database
        $mapped = [];
        foreach ($data as $key => $value) {
            // Convert camelCase to snake_case
            $snakeKey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
            $mapped[$snakeKey] = $value;
        }
        
        return $mapped;
    }

    /**
     * Hydrate entity from array
     * Override to handle password_hash field name mapping
     */
    public function fromArray(array $data): void
    {
        // Map snake_case to camelCase
        $mapped = [];
        foreach ($data as $key => $value) {
            // Convert snake_case to camelCase
            $camelKey = lcfirst(str_replace('_', '', ucwords($key, '_')));
            $mapped[$camelKey] = $value;
        }
        
        // Map password_hash to passwordHash explicitly
        if (isset($mapped['passwordHash'])) {
            // Already mapped
        } elseif (isset($data['password_hash'])) {
            $mapped['passwordHash'] = $data['password_hash'];
        }
        
        parent::fromArray($mapped);
    }
}
