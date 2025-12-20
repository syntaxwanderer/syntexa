<?php

declare(strict_types=1);

namespace Syntexa\UserDomain\Domain\Service;

use Syntexa\Modules\UserDomain\Domain\User;
use Syntexa\UserDomain\Domain\Repository\UserRepositoryInterface;

/**
 * Authentication service
 * Handles user login, logout, and session management
 */
class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * Authenticate user by email and password
     */
    public function authenticate(string $email, string $password): ?User
    {
        $user = $this->userRepository->findByEmail($email);
        if ($user === null) {
            return null;
        }

        if (!$user->verifyPassword($password)) {
            return null;
        }

        return $user;
    }

    /**
     * Start user session
     */
    public function login(User $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = $user->getId();
        $_SESSION['user_email'] = $user->getEmail();
        $_SESSION['user_name'] = $user->getName();
    }

    /**
     * End user session
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_destroy();
        $_SESSION = [];
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['user_id']);
    }

    /**
     * Get current authenticated user
     */
    public function getCurrentUser(): ?User
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return $this->userRepository->findById($_SESSION['user_id']);
    }

    /**
     * Require authentication (throw exception if not authenticated)
     */
    public function requireAuth(): User
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            throw new \RuntimeException('Authentication required');
        }

        return $user;
    }
}

