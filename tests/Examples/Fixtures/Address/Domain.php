<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Fixtures\Address;

/**
 * Clean domain model for Address
 * No persistence-specific fields
 */
class Domain
{
    private ?int $id = null;
    private string $street;
    private string $city;
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

    public function getFullAddress(): string
    {
        return "{$this->street}, {$this->city}, {$this->country}";
    }
}

