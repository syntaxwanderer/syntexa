<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Fixtures\Domain;

class UserDomain
{
    private ?int $id;
    private string $email;
    private ?string $name;
    // Deliberately no addressId property/setter to demonstrate selective mapping

    public function __construct(?int $id = null, string $email = '', ?string $name = null)
    {
        $this->id = $id;
        $this->email = $email;
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
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
}

