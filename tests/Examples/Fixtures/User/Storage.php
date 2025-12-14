<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Fixtures\User;

use Syntexa\Orm\Attributes\AsEntity;
use Syntexa\Orm\Attributes\Column;
use Syntexa\Orm\Attributes\Id;
use Syntexa\Orm\Attributes\GeneratedValue;

#[AsEntity(
    table: 'users',
    domainClass: Domain::class,
    repositoryClass: Repository::class
)]
class Storage
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'int')]
    private ?int $id = null;

    #[Column(type: 'string')]
    private string $email;

    #[Column(type: 'string', nullable: true)]
    private ?string $name = null;

    // Storage-only FK (not exposed in domain by default)
    #[Column(name: 'address_id', type: 'int', nullable: true)]
    private ?int $addressId = null;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getAddressId(): ?int
    {
        return $this->addressId;
    }

    public function setAddressId(?int $addressId): void
    {
        $this->addressId = $addressId;
    }
}

