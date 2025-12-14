<?php

declare(strict_types=1);

namespace Syntexa\Orm\Mapping;

use Syntexa\Orm\Entity\EntityManager;

/**
 * Lazy proxy for related entities
 * Loads the entity on first access
 */
class LazyProxy
{
    private ?object $entity = null;
    private bool $loaded = false;

    public function __construct(
        private EntityManager $em,
        private string $entityClass,
        private ?int $id,
    ) {
    }

    /**
     * Get the loaded entity
     */
    public function getEntity(): ?object
    {
        if (!$this->loaded && $this->id !== null) {
            $this->entity = $this->em->find($this->entityClass, $this->id);
            $this->loaded = true;
        }
        return $this->entity;
    }

    /**
     * Magic method to forward calls to the loaded entity
     */
    public function __call(string $method, array $args): mixed
    {
        $entity = $this->getEntity();
        if ($entity === null) {
            throw new \RuntimeException("Cannot call {$method} on null entity (ID: {$this->id})");
        }
        return $entity->$method(...$args);
    }

    /**
     * Magic method to forward property access to the loaded entity
     */
    public function __get(string $property): mixed
    {
        $entity = $this->getEntity();
        if ($entity === null) {
            return null;
        }
        return $entity->$property ?? null;
    }

    /**
     * Magic method to forward property setting to the loaded entity
     */
    public function __set(string $property, mixed $value): void
    {
        $entity = $this->getEntity();
        if ($entity === null) {
            throw new \RuntimeException("Cannot set {$property} on null entity (ID: {$this->id})");
        }
        $entity->$property = $value;
    }

    /**
     * Check if entity is loaded
     */
    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * Get the entity ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Check if this proxy represents a null relationship
     */
    public function isNull(): bool
    {
        return $this->id === null;
    }
}

