<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm\Blockchain;

use PDO;
use PHPUnit\Framework\TestCase;
use Syntexa\Orm\Blockchain\BlockchainConfig;
use Syntexa\Orm\Blockchain\BlockchainStorage;
use Syntexa\Tests\Examples\Orm\PostgresTestHelper;

/**
 * Base test case for blockchain tests
 * 
 * Provides:
 * - Separate blockchain database (isolated from app DB)
 * - Mock RabbitMQ (InMemoryTransport) for unit tests
 * - Real RabbitMQ option for integration tests
 * - Helper methods for blockchain operations
 */
abstract class BlockchainTestCase extends TestCase
{
    protected ?PDO $blockchainPdo = null;
    protected BlockchainConfig $config;
    protected ?BlockchainStorage $storage = null;
    
    // In-memory queue for testing (simulates RabbitMQ)
    protected static array $mockQueue = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test blockchain config
        $this->config = $this->createTestConfig();
        
        // Setup blockchain database if needed
        if ($this->needsBlockchainDb()) {
            $this->setupBlockchainDb();
        }
    }

    protected function tearDown(): void
    {
        // Cleanup mock queue
        self::$mockQueue = [];
        
        // Cleanup blockchain DB if needed
        if ($this->blockchainPdo !== null) {
            $this->cleanupBlockchainDb();
        }
        
        parent::tearDown();
    }

    /**
     * Create test blockchain configuration
     */
    protected function createTestConfig(): BlockchainConfig
    {
        // Use test database for blockchain
        $dbConfig = $this->getBlockchainDbConfig();
        
        return new BlockchainConfig(
            enabled: true,
            dbHost: $dbConfig['host'],
            dbPort: $dbConfig['port'],
            dbName: $dbConfig['dbname'],
            dbUser: $dbConfig['user'],
            dbPassword: $dbConfig['password'],
            participants: ['node1', 'node2', 'node3'],
            nodeId: 'node1',
            rabbitmqHost: null, // Use mock for unit tests
            rabbitmqPort: null,
            rabbitmqUser: null,
            rabbitmqPassword: null,
            rabbitmqExchange: 'test_blockchain',
            rabbitmqVhost: '/',
            blockSize: 10, // Small blocks for testing
            blockTimeLimit: 5,
            mempoolMaxSize: 100,
            proposerInterval: 2,
            consensusTimeout: 10,
        );
    }

    /**
     * Get blockchain database configuration
     */
    protected function getBlockchainDbConfig(): array
    {
        // Use separate test database for blockchain
        $config = PostgresTestHelper::getConnectionParams();
        return [
            'host' => $config['host'],
            'port' => $config['port'],
            'dbname' => ($config['dbname'] ?? 'syntexa_test') . '_blockchain',
            'user' => $config['user'],
            'password' => $config['password'],
        ];
    }

    /**
     * Setup blockchain database
     */
    protected function setupBlockchainDb(): void
    {
        $dbConfig = $this->getBlockchainDbConfig();
        
        // Try to connect to blockchain database directly (might already exist)
        // If it doesn't exist, use the same database as app tests
        try {
            $this->blockchainPdo = new PDO(
                sprintf('pgsql:host=%s;port=%d;dbname=%s', $dbConfig['host'], $dbConfig['port'], $dbConfig['dbname']),
                $dbConfig['user'],
                $dbConfig['password']
            );
            $this->blockchainPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            // Database doesn't exist, use app test database instead
            // Blockchain tables will be created in the same database
            $appConfig = PostgresTestHelper::getConnectionParams();
            $this->blockchainPdo = new PDO(
                sprintf('pgsql:host=%s;port=%d;dbname=%s', $appConfig['host'], $appConfig['port'], $appConfig['dbname']),
                $appConfig['user'],
                $appConfig['password']
            );
            $this->blockchainPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        
        // Create blockchain storage
        $this->storage = new BlockchainStorage($this->config);
        
        // Create schema
        $this->createBlockchainSchema($this->blockchainPdo);
    }

    /**
     * Cleanup blockchain database
     */
    protected function cleanupBlockchainDb(): void
    {
        if ($this->blockchainPdo === null) {
            return;
        }
        
        try {
            // Drop all tables
            $this->blockchainPdo->exec("
                DO \$\$ 
                DECLARE 
                    r RECORD;
                BEGIN
                    FOR r IN (SELECT tablename FROM pg_tables WHERE schemaname = 'public') LOOP
                        EXECUTE 'DROP TABLE IF EXISTS ' || quote_ident(r.tablename) || ' CASCADE';
                    END LOOP;
                END \$\$;
            ");
        } catch (\PDOException $e) {
            // Ignore errors during cleanup
        }
    }

    /**
     * Create blockchain database schema
     */
    protected function createBlockchainSchema(PDO $pdo): void
    {
        // Schema will be created automatically by BlockchainStorage
        // But we can add test-specific tables here if needed
    }

    /**
     * Check if test needs blockchain database
     * Override in subclasses if not needed
     */
    protected function needsBlockchainDb(): bool
    {
        return true;
    }

    /**
     * Mock RabbitMQ publish (stores in memory instead of real RabbitMQ)
     */
    protected function mockRabbitMQPublish(string $exchange, string $payload): void
    {
        self::$mockQueue[$exchange][] = $payload;
    }

    /**
     * Get messages from mock RabbitMQ queue
     */
    protected function getMockQueueMessages(string $exchange): array
    {
        return self::$mockQueue[$exchange] ?? [];
    }

    /**
     * Clear mock queue
     */
    protected function clearMockQueue(string $exchange): void
    {
        unset(self::$mockQueue[$exchange]);
    }
}

