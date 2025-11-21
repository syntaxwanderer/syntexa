<?php

declare(strict_types=1);

namespace Syntexa\Orm\Entity;

/**
 * Base entity class
 * All entities should extend this class
 */
abstract class BaseEntity
{
    public ?int $id = null;
    public ?\DateTimeImmutable $createdAt = null;
    public ?\DateTimeImmutable $updatedAt = null;

    /**
     * Convert entity to array for storage
     */
    public function toArray(): array
    {
        $data = [];
        $reflection = new \ReflectionClass($this);
        
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }
            
            $property->setAccessible(true);
            $value = $property->getValue($this);
            
            if ($value instanceof \DateTimeImmutable) {
                $value = $value->format('Y-m-d H:i:s');
            }
            
            $data[$property->getName()] = $value;
        }
        
        return $data;
    }

    /**
     * Hydrate entity from array
     */
    public function fromArray(array $data): void
    {
        $reflection = new \ReflectionClass($this);
        
        foreach ($data as $key => $value) {
            if (!$reflection->hasProperty($key)) {
                continue;
            }
            
            $property = $reflection->getProperty($key);
            $property->setAccessible(true);
            
            // Handle DateTimeImmutable
            if ($property->getType()?->getName() === \DateTimeImmutable::class && is_string($value)) {
                $value = new \DateTimeImmutable($value);
            }
            
            $property->setValue($this, $value);
        }
    }

    /**
     * Get entity identifier
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Check if entity is new (not persisted)
     */
    public function isNew(): bool
    {
        return $this->id === null;
    }
}

