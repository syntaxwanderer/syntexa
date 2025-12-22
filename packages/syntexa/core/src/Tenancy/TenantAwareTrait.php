<?php

declare(strict_types=1);

namespace Syntexa\Core\Tenancy;

use Syntexa\Core\Container\RequestScopedContainer;
use DI\Container;

/**
 * Trait for services that need tenant context
 * 
 * Use this trait in repositories, services, or handlers that need
 * to access the current tenant ID for data isolation.
 */
trait TenantAwareTrait
{
    /**
     * Get tenant context from request-scoped container
     * 
     * @throws \RuntimeException If container is not available or tenant context not set
     */
    protected function getTenantContext(): TenantContext
    {
        // Try to get from container if available
        if (property_exists($this, 'container') && $this->container instanceof Container) {
            $requestScoped = \Syntexa\Core\Container\ContainerFactory::getRequestScoped();
            $context = $requestScoped->getTenantContext();
            
            if ($context !== null) {
                return $context;
            }
        }
        
        // Fallback: try to get directly
        $requestScoped = \Syntexa\Core\Container\ContainerFactory::getRequestScoped();
        $context = $requestScoped->getTenantContext();
        
        if ($context === null) {
            throw new \RuntimeException('Tenant context is not available. Make sure tenant resolution is enabled.');
        }
        
        return $context;
    }

    /**
     * Get current tenant ID
     */
    protected function getTenantId(): string
    {
        return $this->getTenantContext()->tenantId;
    }

    /**
     * Check if current tenant is default tenant
     */
    protected function isDefaultTenant(): bool
    {
        return $this->getTenantContext()->isDefault();
    }
}

