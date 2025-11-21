<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Domain\Repository;

use Syntexa\UserFrontend\Domain\Entity\User;

/**
 * User repository interface
 * In the future, this can be implemented with database, but for now we use file storage
 */
interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;
    public function findById(string $id): ?User;
    public function save(User $user): void;
    public function exists(string $email): bool;
}

/**
 * File-based user repository (simple implementation)
 */
class UserRepository implements UserRepositoryInterface
{
    private string $storageFile;

    public function __construct()
    {
        $projectRoot = $this->getProjectRoot();
        $this->storageFile = $projectRoot . '/var/data/users.json';
        $this->ensureStorageFile();
    }

    public function findByEmail(string $email): ?User
    {
        $users = $this->loadUsers();
        return $users[$email] ?? null;
    }

    public function findById(string $id): ?User
    {
        $users = $this->loadUsers();
        foreach ($users as $user) {
            if ($user->id === $id) {
                return $user;
            }
        }
        return null;
    }

    public function save(User $user): void
    {
        $users = $this->loadUsers();
        $users[$user->email] = $user;
        $this->saveUsers($users);
    }

    public function exists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    private function loadUsers(): array
    {
        if (!file_exists($this->storageFile)) {
            return [];
        }

        $content = file_get_contents($this->storageFile);
        if (empty($content)) {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }

        $users = [];
        foreach ($data as $userData) {
            $user = User::fromArray($userData);
            $users[$user->email] = $user;
        }

        return $users;
    }

    private function saveUsers(array $users): void
    {
        $data = [];
        foreach ($users as $user) {
            $data[] = $user->toArray();
        }

        $dir = dirname($this->storageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->storageFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function ensureStorageFile(): void
    {
        $dir = dirname($this->storageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!file_exists($this->storageFile)) {
            file_put_contents($this->storageFile, '[]');
        }
    }

    private function getProjectRoot(): string
    {
        $dir = __DIR__;
        while ($dir !== '/' && $dir !== '') {
            if (file_exists($dir . '/composer.json')) {
                if (is_dir($dir . '/src/modules')) {
                    return $dir;
                }
            }
            $dir = dirname($dir);
        }

        return dirname(__DIR__, 8);
    }
}

