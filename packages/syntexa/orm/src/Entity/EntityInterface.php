<?php

declare(strict_types=1);

namespace Syntexa\Orm\Entity;

/**
 * Entity interface
 */
interface EntityInterface
{
    /**
     * Get entity identifier
     */
    public function getId(): ?int;

    /**
     * Check if entity is new (not persisted)
     */
    public function isNew(): bool;
}

