<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Fixtures\Post;

use Syntexa\Tests\Examples\Fixtures\User\Domain as UserDomain;

/**
 * Clean domain model for Post
 * Supports both ID-based and object-based relationship access
 */
class Domain
{
    private ?int $id = null;
    private string $title;
    private string $content;
    private ?int $userId = null; // FK to User (for backward compatibility)
    /** @var UserDomain|\Syntexa\Orm\Mapping\LazyProxy|null */
    private $user = null; // Related User object (lazy loaded, can be LazyProxy or UserDomain)

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * Get related User object (lazy loaded)
     * Returns UserDomain or LazyProxy
     */
    public function getUser(): ?UserDomain
    {
        // If it's a LazyProxy, get the actual entity
        if ($this->user instanceof \Syntexa\Orm\Mapping\LazyProxy) {
            $entity = $this->user->getEntity();
            $this->user = $entity; // Replace proxy with actual entity
            return $entity;
        }
        return $this->user;
    }

    /**
     * Set related User object (can be UserDomain or LazyProxy)
     */
    public function setUser(UserDomain|\Syntexa\Orm\Mapping\LazyProxy|null $user): void
    {
        $this->user = $user;
        if ($user !== null) {
            if ($user instanceof \Syntexa\Orm\Mapping\LazyProxy) {
                $id = $user->getId();
                if ($id !== null) {
                    $this->userId = $id;
                }
            } else {
                $this->userId = $user->getId();
            }
        }
    }
}

