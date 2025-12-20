<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm;

use DI\Container;
use DI\ContainerBuilder;
use DI\Definition\Helper\AutowireDefinitionHelper;
use Syntexa\Orm\Entity\EntityManager;
use function DI\value;

/**
 * Helper class for autowiring in tests
 * Wraps DI\autowire() function to use class instead of function
 */
class Autowire
{
    /**
     * Create autowire definition for a class
     * 
     * @param string $className Class name to autowire
     * @return AutowireDefinitionHelper
     */
    public static function class(string $className): AutowireDefinitionHelper
    {
        return \DI\autowire($className);
    }

    /**
     * Get repository instance via DI container
     * 
     * This is the recommended way to get repositories in tests,
     * as it uses the same DI pattern as the application.
     * 
     * @template T
     * @param class-string<T> $repositoryClass Repository class name
     * @param EntityManager $em EntityManager instance
     * @return T Repository instance
     */
    public static function repository(string $repositoryClass, EntityManager $em): object
    {
        $builder = new ContainerBuilder();
        
        // Enable autowiring so we can use class names directly
        $builder->useAutowiring(true);
        $builder->useAttributes(true);

        $definitions = [
            EntityManager::class => value($em),
            $repositoryClass => self::class($repositoryClass),
        ];

        $builder->addDefinitions($definitions);
        $container = $builder->build();
        
        return $container->get($repositoryClass);
    }
}
