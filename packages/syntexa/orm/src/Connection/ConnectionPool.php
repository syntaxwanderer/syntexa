<?php

declare(strict_types=1);

namespace Syntexa\Orm\Connection;

use Swoole\Database\PDOPool;
use Swoole\Database\PDOConfig;

/**
 * Static Connection Pool Manager
 * Used to manage database connections in a Swoole environment
 */
class ConnectionPool
{
    private static ?PDOPool $pool = null;
    private static array $config = [];

    /**
     * Initialize the connection pool
     * 
     * @param array $config Database configuration
     */
    public static function initialize(array $config): void
    {
        if (self::$pool !== null) {
            return;
        }

        self::$config = $config;

        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Swoole extension is required for ConnectionPool');
        }

        $pdoConfig = new PDOConfig();
        $pdoConfig->withDriver('pgsql');
        $pdoConfig->withHost($config['host'] ?? 'localhost');
        $pdoConfig->withPort((int) ($config['port'] ?? 5432));
        $pdoConfig->withDbname($config['dbname'] ?? 'syntexa');
        $pdoConfig->withUsername($config['user'] ?? 'postgres');
        $pdoConfig->withPassword($config['password'] ?? '');

        // Standard PDO options
        $pdoConfig->withOptions([
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        self::$pool = new PDOPool($pdoConfig, $config['pool_size'] ?? 10);
    }

    /**
     * Get a connection from the pool
     * 
     * @return object PDOProxy object
     */
    public static function get(): object
    {
        if (self::$pool === null) {
            throw new \RuntimeException('ConnectionPool not initialized. Call initialize() first.');
        }

        return self::$pool->get();
    }

    /**
     * Put a connection back into the pool
     * 
     * @param object $connection PDOProxy object
     */
    public static function put(object $connection): void
    {
        if (self::$pool !== null) {
            self::$pool->put($connection);
        }
    }

    /**
     * Close the connection pool
     */
    public static function close(): void
    {
        if (self::$pool !== null) {
            self::$pool->close();
            self::$pool = null;
        }
    }
}
