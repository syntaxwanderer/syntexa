<?php

declare(strict_types=1);

namespace Syntexa\Orm\Mapping;

use Syntexa\Orm\Metadata\EntityMetadata;

interface DomainMapperInterface
{
    /**
     * Map storage entity to domain object.
     */
    public function toDomain(object $storage, EntityMetadata $metadata, DomainContext $context): object;

    /**
     * Map domain object back to storage entity (updates and returns storage).
     */
    public function toStorage(object $domain, object $storage, EntityMetadata $metadata, DomainContext $context): object;
}

