<?php

declare(strict_types=1);

namespace Syntexa\UserDomain\Domain\Repository;

use Syntexa\UserDomain\Domain\Entity\User;
use Syntexa\Orm\Entity\EntityManager;
use DI\Attribute\Inject;

/**
 * User repository interface
 */
interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;
    public function findById(int $id): ?User;
    public function save(User $user): User;
    public function exists(string $email): bool;
}

/**
 * Database-based user repository using EntityManager
 */
class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        #[Inject] private EntityManager $em
    ) {
    }

    public function findByEmail(string $email): ?User
    {
        return $this->em->findOneBy(User::class, ['email' => $email]);
    }

    public function findById(int $id): ?User
    {
        return $this->em->find(User::class, $id);
    }

    public function save(User $user): User
    {
        return $this->em->save($user);
    }

    public function exists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }
}
