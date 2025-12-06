<?php

declare(strict_types=1);

namespace Syntexa\Orm\Entity;

use Syntexa\Orm\Attributes\Column;
use Syntexa\Orm\Attributes\GeneratedValue;
use Syntexa\Orm\Attributes\Id;

/**
 * Base entity class
 * All entities should extend this class
 */
abstract class BaseEntity implements EntityInterface
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'id', type: 'int', nullable: true)]
    protected ?int $id = null;

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

