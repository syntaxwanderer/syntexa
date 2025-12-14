<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Fixtures\Post;

use Syntexa\Orm\Attributes\AsEntity;
use Syntexa\Orm\Attributes\Column;
use Syntexa\Orm\Attributes\Id;
use Syntexa\Orm\Attributes\GeneratedValue;
use Syntexa\Orm\Attributes\ManyToOne;
use Syntexa\Orm\Attributes\JoinColumn;

#[AsEntity(
    table: 'posts',
    domainClass: Domain::class
)]
class Storage
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'int')]
    private ?int $id = null;

    #[Column(type: 'string')]
    private string $title;

    #[Column(type: 'text')]
    private string $content;

    // Foreign key column
    #[Column(name: 'user_id', type: 'int')]
    private ?int $userId = null;

    // ManyToOne relationship (virtual property, FK stored in userId)
    #[ManyToOne(targetEntity: \Syntexa\Tests\Examples\Fixtures\User\Storage::class, fetch: 'lazy')]
    #[JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?object $user = null; // Virtual property for relationship

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
}

