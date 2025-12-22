<?php

declare(strict_types=1);

namespace Syntexa\Core\Tenancy;

/**
 * Tenant Context - Request-scoped tenant identification
 * 
 * This class holds the current tenant ID for the request.
 * It's request-scoped to prevent data leakage between tenants in Swoole.
 */
readonly class TenantContext
{
    public function __construct(
        public string $tenantId,
        public string $strategy, // 'header', 'host', 'path'
        public ?string $source = null // Original source value (header value, hostname, path segment)
    ) {}

    /**
     * Check if tenant is set (not default)
     */
    public function isDefault(): bool
    {
        return $this->tenantId === 'default';
    }

    /**
     * Get tenant ID or throw if not set
     */
    public function requireTenantId(): string
    {
        if ($this->isDefault()) {
            throw new \RuntimeException('Tenant ID is required but not set in request context');
        }
        return $this->tenantId;
    }
}

