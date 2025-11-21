<?php

declare(strict_types=1);

namespace Syntexa\Core\Container;

use DI\Container;
use DI\ContainerBuilder;

/**
 * Factory for creating DI container
 * Configured for Swoole long-running processes
 */
class ContainerFactory
{
    private static ?Container $container = null;

    /**
     * Get or create the container instance
     * In Swoole, this should be called once per worker
     */
    public static function create(): Container
    {
        if (self::$container === null) {
            $builder = new ContainerBuilder();
            
            // Enable compilation for better performance (optional)
            // $builder->enableCompilation(__DIR__ . '/../../../../var/cache/container');
            
            // Load definitions
            $builder->addDefinitions(self::getDefinitions());
            
            self::$container = $builder->build();
        }

        return self::$container;
    }

    /**
     * Reset the container (call after each request in Swoole)
     * 
     * Note: PHP-DI doesn't have a built-in reset() method.
     * Instead, we use factory functions for request-scoped services
     * and singleton pattern only for infrastructure services.
     * 
     * This method is kept for compatibility but does nothing.
     * The container is designed to be safe for Swoole by using
     * factory functions that create new instances for each request.
     */
    public static function reset(): void
    {
        // PHP-DI doesn't have reset(), but we use factory functions
        // for request-scoped services, so no reset is needed.
        // Infrastructure services (singletons) are safe to persist.
    }

    /**
     * Get container definitions
     * Can be extended by modules
     * 
     * IMPORTANT FOR SWOOLE:
     * - Use factory() for request-scoped services (creates new instance each time)
     * - Use create() only for infrastructure singletons that are safe to persist
     */
    private static function getDefinitions(): array
    {
        $definitions = [];

        // Core services - Environment is immutable, so singleton is safe
        $definitions[\Syntexa\Core\Environment::class] = \DI\factory(function () {
            return \Syntexa\Core\Environment::create();
        });

        // Infrastructure services - singleton is safe (stateless or connection pool)
        $definitions[\Syntexa\Core\Queue\QueueTransportRegistry::class] = \DI\factory(function () {
            $registry = new \Syntexa\Core\Queue\QueueTransportRegistry();
            $registry->initialize();
            return $registry;
        });

        // Example: Request-scoped service (new instance each request)
        // $definitions[\Syntexa\Core\Container\ExampleService::class] = \DI\factory(function () {
        //     return new \Syntexa\Core\Container\ExampleService();
        // });

        // Example: Infrastructure singleton (safe to persist)
        // $definitions[\Syntexa\Core\Database\ConnectionPool::class] = \DI\create()
        //     ->constructor(\DI\get('db.config'));

        // Add module-specific definitions here
        // Modules can extend this via service providers

        return $definitions;
    }

    /**
     * Get the singleton container instance
     */
    public static function get(): Container
    {
        return self::create();
    }

    /**
     * Get request-scoped container wrapper
     * Use this in Application for resolving handlers
     */
    public static function getRequestScoped(): RequestScopedContainer
    {
        return new RequestScopedContainer(self::create());
    }
}

