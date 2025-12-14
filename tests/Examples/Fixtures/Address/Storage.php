<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Fixtures\Address;

use Syntexa\Orm\Attributes\AsEntity;
use Syntexa\Orm\Attributes\Column;
use Syntexa\Orm\Attributes\Id;
use Syntexa\Orm\Attributes\GeneratedValue;

#[AsEntity(
    table: 'addresses',
    domainClass: Domain::class
)]
class Storage
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'int')]
    private ?int $id = null;

    #[Column(type: 'string')]
    private string $street;

    #[Column(type: 'string')]
    private string $city;

    #[Column(type: 'string')]
    private string $country;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function setStreet(string $street): void
    {
        $this->street = $street;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }
}

