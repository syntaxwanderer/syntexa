<?php

declare(strict_types=1);

namespace Syntexa\Orm\Entity\Traits;

use Syntexa\Orm\Attributes\Column;
use Syntexa\Orm\Attributes\TimestampColumn;

trait TimestampedEntityTrait
{
    #[Column(name: 'created_at', type: 'datetime_immutable', nullable: true)]
    #[TimestampColumn(TimestampColumn::TYPE_CREATED)]
    private ?\DateTimeImmutable $createdAt = null;

    #[Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    #[TimestampColumn(TimestampColumn::TYPE_UPDATED)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * Update the updatedAt field to the current time.
     */
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Initialize both timestamps when creating a new record.
     */
    public function initializeTimestamps(): void
    {
        $now = new \DateTimeImmutable();
        if ($this->createdAt === null) {
            $this->createdAt = $now;
        }
        $this->updatedAt = $now;
    }
}


