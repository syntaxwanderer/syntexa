<?php

declare(strict_types=1);

namespace Syntexa\Core\Queue;

use Syntexa\Core\Queue\Transport\InMemoryTransportFactory;
use Syntexa\Core\Queue\Transport\RabbitMqTransportFactory;

class QueueTransportRegistry
{
    /**
     * @var array<string, QueueTransportFactoryInterface>
     */
    private static array $factories = [];

    /**
     * @var array<string, QueueTransportInterface>
     */
    private static array $instances = [];

    private static bool $initialized = false;

    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        self::register('in-memory', new InMemoryTransportFactory());
        self::register('memory', new InMemoryTransportFactory());
        self::register('rabbitmq', new RabbitMqTransportFactory());

        self::$initialized = true;
    }

    public static function register(string $name, QueueTransportFactoryInterface $factory): void
    {
        self::$factories[strtolower($name)] = $factory;
    }

    public static function create(string $name): QueueTransportInterface
    {
        $key = strtolower($name);
        self::initialize();

        if (!isset(self::$instances[$key])) {
            if (!isset(self::$factories[$key])) {
                throw new \RuntimeException("Queue transport '{$name}' is not registered");
            }
            self::$instances[$key] = self::$factories[$key]->create();
        }

        return self::$instances[$key];
    }
}

