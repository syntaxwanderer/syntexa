<?php

declare(strict_types=1);

namespace Syntexa\Core\Container;

use DI\Container;

/**
 * Wrapper for request-scoped services in Swoole
 * 
 * This ensures that services that should be request-scoped
 * are created fresh for each request, preventing data leakage.
 */
class RequestScopedContainer
{
    private Container $container;
    private array $requestScopedCache = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Get a service - creates new instance for request-scoped services
     */
    public function get(string $id): mixed
    {
        // Check if this is a request-scoped service
        if ($this->isRequestScoped($id)) {
            // Always create new instance for request-scoped services
            return $this->container->make($id);
        }

        // Use singleton for infrastructure services
        return $this->container->get($id);
    }

    /**
     * Check if service should be request-scoped
     */
    private function isRequestScoped(string $id): bool
    {
        // Services that handle request data should be request-scoped
        $requestScopedPatterns = [
            'Handler',
            'Service', // Most services should be request-scoped
            'Repository', // If they cache data
        ];

        foreach ($requestScopedPatterns as $pattern) {
            if (str_contains($id, $pattern)) {
                return true;
            }
        }

        // Infrastructure services are singletons
        $singletonPatterns = [
            'Environment',
            'Registry',
            'Factory',
            'Pool', // Connection pools
        ];

        foreach ($singletonPatterns as $pattern) {
            if (str_contains($id, $pattern)) {
                return false;
            }
        }

        // Default: request-scoped for safety
        return true;
    }

    /**
     * Reset request-scoped cache (call after each request)
     */
    public function reset(): void
    {
        $this->requestScopedCache = [];
    }
}

