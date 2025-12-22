<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm\Blockchain;

use PHPUnit\Framework\TestCase;
use Syntexa\Tests\Examples\Orm\PostgresTestHelper;

/**
 * Multi-Node Blockchain Test Case
 * 
 * Base test case for testing blockchain with multiple nodes.
 * 
 * Provides:
 * - Multiple node simulators (each with own database)
 * - Shared RabbitMQ connection
 * - Helper methods for multi-node operations
 * - Automatic cleanup
 * 
 * Usage:
 * ```php
 * class MyMultiNodeTest extends MultiNodeTestCase
 * {
 *     public function testMultiNodeConsensus(): void
 *     {
 *         // Nodes are available as $this->nodes['node1'], $this->nodes['node2'], etc.
 *         // RabbitMQ is shared across all nodes
 *         
 *         // Publish transaction from node1
 *         $tx = $this->createTestTransaction('node1');
 *         $this->nodes['node1']->publishTransaction($tx);
 *         
 *         // Consume from all nodes
 *         $this->consumeFromAllNodes();
 *         
 *         // Verify all nodes have the transaction
 *         $this->assertAllNodesHaveTransaction($tx);
 *     }
 * }
 * ```
 */
abstract class MultiNodeTestCase extends TestCase
{
    /**
     * @var array<string, NodeSimulator> Node simulators indexed by node ID
     */
    protected array $nodes = [];

    /**
     * RabbitMQ configuration
     */
    protected array $rabbitmqConfig = [];

    /**
     * Number of nodes to create (override in subclasses)
     */
    protected int $nodeCount = 2;

    /**
     * Node IDs (override in subclasses)
     */
    protected array $nodeIds = ['node1', 'node2'];

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure RabbitMQ extension is available
        if (!class_exists(\AMQPConnection::class)) {
            $this->fail('ext-amqp is required for multi-node blockchain tests. Install it: pecl install amqp');
        }

        // Ensure PostgreSQL is available - bootstrap should have started it
        if (!PostgresTestHelper::isEnabled()) {
            $this->fail('Multi-node blockchain tests require PostgreSQL. Set TEST_WITH_SQLITE=0 or ensure PostgreSQL is available.');
        }
        
        // Ensure PostgreSQL container is running - PostgresTestHelper will start it if needed
        if (!PostgresTestHelper::ensureContainerRunning()) {
            $this->fail('PostgreSQL container is not available. Ensure Docker containers are running (bootstrap should start them automatically). Run: docker-compose -f docker-compose.test.yml up -d');
        }
        
        // Verify connection works
        $pdo = PostgresTestHelper::createConnection();
        if ($pdo === null) {
            $this->fail('PostgreSQL connection failed. Ensure Docker containers are running and database exists.');
        }
        
        $driverName = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driverName !== 'pgsql') {
            $this->fail('Multi-node blockchain tests require PostgreSQL. Ensure TEST_WITH_SQLITE is not set.');
        }

        // Setup RabbitMQ config
        $this->rabbitmqConfig = $this->getRabbitMQConfig();

        // Create nodes
        $this->createNodes();
    }

    protected function tearDown(): void
    {
        // Cleanup all nodes
        foreach ($this->nodes as $node) {
            $node->cleanup();
            $node->close();
        }
        $this->nodes = [];

        parent::tearDown();
    }

    /**
     * Create all nodes
     */
    protected function createNodes(): void
    {
        $participants = $this->nodeIds;

        foreach ($this->nodeIds as $index => $nodeId) {
            $dbConfig = $this->getNodeDbConfig($nodeId, $index);
            
            $node = new NodeSimulator(
                nodeId: $nodeId,
                dbConfig: $dbConfig,
                rabbitmqConfig: $this->rabbitmqConfig,
                participants: $participants,
            );

            $this->nodes[$nodeId] = $node;
        }
    }

    /**
     * Get database configuration for a specific node
     */
    protected function getNodeDbConfig(string $nodeId, int $index): array
    {
        $baseConfig = PostgresTestHelper::getConnectionParams();
        
        // For multi-node tests, we use different ports for each node's blockchain DB
        // Port mapping: 5434 for node1, 5435 for node2, etc.
        $port = 5434 + $index;
        
        return [
            'host' => $baseConfig['host'],
            'port' => $port,
            'dbname' => ($baseConfig['dbname'] ?? 'syntexa_test') . '_' . $nodeId . '_blockchain',
            'user' => $baseConfig['user'],
            'password' => $baseConfig['password'],
        ];
    }

    /**
     * Get RabbitMQ configuration
     */
    protected function getRabbitMQConfig(): array
    {
        // Try to connect to RabbitMQ (default test port 5673)
        $host = $_ENV['BLOCKCHAIN_RABBITMQ_HOST'] ?? 'localhost';
        $port = (int) ($_ENV['BLOCKCHAIN_RABBITMQ_PORT'] ?? 5673);
        $user = $_ENV['BLOCKCHAIN_RABBITMQ_USER'] ?? 'test';
        $password = $_ENV['BLOCKCHAIN_RABBITMQ_PASSWORD'] ?? 'test';
        $exchange = $_ENV['BLOCKCHAIN_RABBITMQ_EXCHANGE'] ?? 'syntexa_blockchain_test';
        $vhost = $_ENV['BLOCKCHAIN_RABBITMQ_VHOST'] ?? '/';

        return [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $password,
            'exchange' => $exchange,
            'vhost' => $vhost,
        ];
    }


    /**
     * Consume transactions from all nodes
     * 
     * @param float $timeout Timeout in seconds
     * @return array<string, int> Number of consumed transactions per node
     */
    protected function consumeFromAllNodes(float $timeout = 2.0): array
    {
        $consumed = [];
        
        foreach ($this->nodes as $nodeId => $node) {
            $consumed[$nodeId] = $node->consumeAllTransactions($timeout);
        }
        
        return $consumed;
    }

    /**
     * Wait for all nodes to consume transactions
     * 
     * Polls all nodes until they've consumed expected number of transactions
     * or timeout is reached.
     */
    protected function waitForAllNodesToConsume(int $expectedCount, float $timeout = 5.0): bool
    {
        $startTime = microtime(true);
        
        while ((microtime(true) - $startTime) < $timeout) {
            $allConsumed = true;
            
            foreach ($this->nodes as $node) {
                $txCount = count($node->getBlockchainTransactions());
                if ($txCount < $expectedCount) {
                    $allConsumed = false;
                    // Try to consume more
                    $node->consumeAllTransactions(0.1);
                }
            }
            
            if ($allConsumed) {
                return true;
            }
            
            usleep(50000); // 50ms
        }
        
        return false;
    }

    /**
     * Assert that all nodes have the same number of transactions
     */
    protected function assertAllNodesHaveSameTransactionCount(?int $expectedCount = null): void
    {
        $counts = [];
        foreach ($this->nodes as $nodeId => $node) {
            $counts[$nodeId] = count($node->getBlockchainTransactions());
        }

        if ($expectedCount !== null) {
            foreach ($counts as $nodeId => $count) {
                $this->assertSame(
                    $expectedCount,
                    $count,
                    "Node {$nodeId} should have {$expectedCount} transactions, but has {$count}"
                );
            }
        } else {
            // Check all nodes have the same count
            $firstCount = reset($counts);
            foreach ($counts as $nodeId => $count) {
                $this->assertSame(
                    $firstCount,
                    $count,
                    "All nodes should have the same transaction count. Node {$nodeId} has {$count}, expected {$firstCount}"
                );
            }
        }
    }

    /**
     * Assert that a specific transaction exists in all nodes
     */
    protected function assertAllNodesHaveTransaction(string $transactionId): void
    {
        foreach ($this->nodes as $nodeId => $node) {
            $transactions = $node->getBlockchainTransactions();
            $found = false;
            
            foreach ($transactions as $tx) {
                if ($tx['transaction_id'] === $transactionId) {
                    $found = true;
                    break;
                }
            }
            
            $this->assertTrue(
                $found,
                "Transaction {$transactionId} should exist in node {$nodeId}"
            );
        }
    }

    /**
     * Get transaction count for all nodes
     */
    protected function getTransactionCounts(): array
    {
        $counts = [];
        foreach ($this->nodes as $nodeId => $node) {
            $counts[$nodeId] = count($node->getBlockchainTransactions());
        }
        return $counts;
    }

    /**
     * Get a specific node simulator
     */
    protected function getNode(string $nodeId): NodeSimulator
    {
        if (!isset($this->nodes[$nodeId])) {
            throw new \InvalidArgumentException("Node {$nodeId} does not exist");
        }
        return $this->nodes[$nodeId];
    }
}

