<?php

declare(strict_types=1);

namespace Syntexa\Orm\Mapping;

/**
 * Per-request mapping context (identity caches and bookkeeping).
 */
class DomainContext
{
    /**
     * @var array<string, object> storage identity map keyed by "class#id"
     */
    public array $storageById = [];

    /**
     * @var array<string, object> domain identity map keyed by "class#id"
     */
    public array $domainById = [];

    /**
     * @var array<int, object> domain object id -> storage object
     */
    public array $domainToStorage = [];

    /**
     * @var array<int, object> storage object id -> domain object
     */
    public array $storageToDomain = [];
}

