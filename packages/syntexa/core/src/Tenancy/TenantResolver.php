<?php

declare(strict_types=1);

namespace Syntexa\Core\Tenancy;

use Syntexa\Core\Request;
use Syntexa\Core\Environment;

/**
 * Tenant Resolver - Resolves tenant ID from request
 * 
 * Supports multiple strategies:
 * - header: Extract from HTTP header (e.g., X-Tenant-ID)
 * - host:   Extract from subdomain (e.g., tenant1.example.com)
 * - path:   Extract from first path segment (e.g., /tenant1/...)
 */
class TenantResolver
{
    public function __construct(
        private readonly Environment $environment
    ) {}

    /**
     * Resolve tenant from request
     */
    public function resolve(Request $request): TenantContext
    {
        $strategy = $this->environment->tenantStrategy;
        $defaultTenant = $this->environment->tenantDefault;

        return match ($strategy) {
            'header' => $this->resolveFromHeader($request, $defaultTenant),
            'host' => $this->resolveFromHost($request, $defaultTenant),
            'path' => $this->resolveFromPath($request, $defaultTenant),
            default => new TenantContext($defaultTenant, $strategy, null)
        };
    }

    /**
     * Resolve tenant from HTTP header
     */
    private function resolveFromHeader(Request $request, string $defaultTenant): TenantContext
    {
        $headerName = $this->environment->tenantHeader;
        $headerValue = $request->getHeader($headerName);

        if ($headerValue === null || $headerValue === '') {
            return new TenantContext($defaultTenant, 'header', null);
        }

        // Sanitize tenant ID (alphanumeric, dash, underscore only)
        $tenantId = $this->sanitizeTenantId($headerValue);
        
        return new TenantContext($tenantId, 'header', $headerValue);
    }

    /**
     * Resolve tenant from hostname/subdomain
     */
    private function resolveFromHost(Request $request, string $defaultTenant): TenantContext
    {
        $host = $request->getServer('HTTP_HOST') ?? $request->getServer('SERVER_NAME') ?? '';
        
        if ($host === '') {
            return new TenantContext($defaultTenant, 'host', null);
        }

        // Extract subdomain (e.g., tenant1.example.com -> tenant1)
        $parts = explode('.', $host);
        if (count($parts) < 2) {
            return new TenantContext($defaultTenant, 'host', $host);
        }

        $subdomain = $parts[0];
        $tenantId = $this->sanitizeTenantId($subdomain);

        return new TenantContext($tenantId, 'host', $host);
    }

    /**
     * Resolve tenant from path segment
     */
    private function resolveFromPath(Request $request, string $defaultTenant): TenantContext
    {
        $path = $request->getPath();
        
        // Remove leading slash and get first segment
        $path = ltrim($path, '/');
        if ($path === '') {
            return new TenantContext($defaultTenant, 'path', null);
        }

        $segments = explode('/', $path);
        $firstSegment = $segments[0];

        if ($firstSegment === '' || $firstSegment === 'api' || $firstSegment === 'admin') {
            // Skip common path prefixes
            return new TenantContext($defaultTenant, 'path', $path);
        }

        $tenantId = $this->sanitizeTenantId($firstSegment);

        return new TenantContext($tenantId, 'path', $path);
    }

    /**
     * Sanitize tenant ID (alphanumeric, dash, underscore only)
     */
    private function sanitizeTenantId(string $value): string
    {
        // Remove any invalid characters
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $value);
        
        // Ensure it's not empty
        if ($sanitized === '') {
            return 'default';
        }

        // Limit length (prevent abuse)
        if (strlen($sanitized) > 64) {
            $sanitized = substr($sanitized, 0, 64);
        }

        return $sanitized;
    }
}

