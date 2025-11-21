<?php

declare(strict_types=1);

namespace Syntexa\Orm\Connection;

use PDO;
use Swoole\Database\PDOPool;
use Swoole\Database\PDOConfig;

/**
 * PostgreSQL Connection Pool for Swoole
 * Singleton - safe to persist across requests
 */
class ConnectionPool
{
    private static ?PDOPool $pool = null;
    private static array $config = [];

    /**
     * Initialize connection pool
     * Should be called once during application bootstrap
     */
    public static function initialize(array $config): void
    {
        if (self::$pool !== null) {
            return; // Already initialized
        }

        self::$config = $config;

        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Swoole extension is required for ConnectionPool');
        }

        $poolSize = $config['pool_size'] ?? 10;
        $dsn = self::buildDsn($config);
        $user = $config['user'] ?? 'postgres';
        $password = $config['password'] ?? '';

        // Create PDOConfig for PostgreSQL
        $pdoConfig = new PDOConfig();
        $pdoConfig->withDriver('pgsql'); // Set PostgreSQL driver
        $pdoConfig->withHost($config['host'] ?? 'localhost');
        $pdoConfig->withPort($config['port'] ?? 5432);
        $pdoConfig->withDbname($config['dbname'] ?? 'postgres'); // Note: withDbname (lowercase 'n')
        $pdoConfig->withUsername($user);
        $pdoConfig->withPassword($password);
        // PostgreSQL charset is set via client_encoding, not in DSN
        $pdoConfig->withOptions([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        self::$pool = new PDOPool($pdoConfig, $poolSize);
    }

    /**
     * Get a connection from the pool
     * Returns PDO or PDOProxy instance (Swoole wraps PDO in PDOProxy)
     * PDOProxy implements PDO interface, so it's compatible
     */
    public static function get()
    {
        if (self::$pool === null) {
            throw new \RuntimeException('ConnectionPool not initialized. Call ConnectionPool::initialize() first.');
        }

        // Swoole PDOPool returns PDOProxy, but it implements PDO interface
        // Return type is mixed to allow both PDO and PDOProxy
        return self::$pool->get();
    }

    /**
     * Return connection to the pool
     */
    public static function put(PDO $connection): void
    {
        if (self::$pool === null) {
            return;
        }

        self::$pool->put($connection);
    }

    /**
     * Build PostgreSQL DSN
     * Note: PostgreSQL doesn't support charset in DSN, use client_encoding instead
     */
    private static function buildDsn(array $config): string
    {
        $parts = [];
        
        if (isset($config['host'])) {
            $parts[] = 'host=' . $config['host'];
        }
        
        if (isset($config['port'])) {
            $parts[] = 'port=' . $config['port'];
        }
        
        if (isset($config['dbname'])) {
            $parts[] = 'dbname=' . $config['dbname'];
        }
        
        // PostgreSQL doesn't support charset in DSN
        // Encoding is set via client_encoding after connection if needed

        return 'pgsql:' . implode(';', $parts);
    }

    /**
     * Get pool statistics
     */
    public static function getStats(): array
    {
        if (self::$pool === null) {
            return ['initialized' => false];
        }

        // PDOPool doesn't expose stats directly, but we can track our own
        return [
            'initialized' => true,
            'pool_size' => self::$config['pool_size'] ?? 10,
        ];
    }
}

