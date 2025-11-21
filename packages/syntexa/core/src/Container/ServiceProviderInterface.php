<?php

declare(strict_types=1);

namespace Syntexa\Core\Container;

/**
 * Interface for service providers
 * Modules can implement this to register their services
 */
interface ServiceProviderInterface
{
    /**
     * Register services in the container
     * 
     * @return array Container definitions
     */
    public function getDefinitions(): array;
}

