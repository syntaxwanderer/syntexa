<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm\Blockchain;

use Syntexa\Orm\Blockchain\BlockchainTransaction;
use Syntexa\Orm\Blockchain\TransactionIdGenerator;
use Syntexa\Tests\Examples\Fixtures\User\Storage as UserStorage;

/**
 * Multi-Node Blockchain Integration Test
 * 
 * Tests blockchain functionality with multiple nodes:
 * - node1: Shop/Store node (publishes transactions)
 * - node2: Trusted blockchain server (consumes and validates)
 * 
 * This test simulates a real-world scenario where:
 * 1. Shop node saves entities and publishes to blockchain
 * 2. Trusted server consumes transactions and stores them
 * 3. Both nodes maintain their own blockchain database
 * 4. All nodes share the same RabbitMQ exchange
 */
class MultiNodeBlockchainTest extends MultiNodeTestCase
{
    protected int $nodeCount = 2;
    protected array $nodeIds = ['shop', 'trusted-server'];

    /**
     * Test: Shop publishes transaction, trusted server consumes it
     */
    public function testShopPublishesTrustedServerConsumes(): void
    {
        // Create transaction from shop node
        $shopNode = $this->getNode('shop');
        $trustedNode = $this->getNode('trusted-server');

        $transaction = $this->createTestTransaction(
            nodeId: 'shop',
            entityClass: UserStorage::class,
            entityId: 1,
            operation: 'save',
            fields: ['email' => 'test@example.com']
        );

        // Shop publishes transaction
        $shopNode->publishTransaction($transaction);

        // Trusted server consumes transaction
        $consumed = $trustedNode->consumeOneTransaction(2.0);
        $this->assertTrue($consumed, 'Trusted server should consume transaction from RabbitMQ');

        // Verify transaction is stored in trusted server's blockchain
        $transactions = $trustedNode->getBlockchainTransactions();
        $this->assertCount(1, $transactions, 'Trusted server should have 1 transaction');
        $this->assertSame($transaction->transactionId, $transactions[0]['transaction_id']);

        // Shop node should also have the transaction (if it consumes its own messages)
        // But typically shop only publishes, so we don't assert this
    }

    /**
     * Test: Multiple transactions published by shop, all consumed by trusted server
     */
    public function testMultipleTransactionsConsumed(): void
    {
        $shopNode = $this->getNode('shop');
        $trustedNode = $this->getNode('trusted-server');

        // Publish 3 transactions
        $transactions = [];
        for ($i = 1; $i <= 3; $i++) {
            $tx = $this->createTestTransaction(
                nodeId: 'shop',
                entityClass: UserStorage::class,
                entityId: $i,
                operation: 'save',
                fields: ['email' => "user{$i}@example.com"]
            );
            $shopNode->publishTransaction($tx);
            $transactions[] = $tx;
        }

        // Consume all transactions
        $consumed = $trustedNode->consumeAllTransactions(2.0);
        $this->assertGreaterThanOrEqual(3, $consumed, 'Trusted server should consume at least 3 transactions');

        // Verify all transactions are stored
        $storedTransactions = $trustedNode->getBlockchainTransactions();
        $this->assertGreaterThanOrEqual(3, count($storedTransactions), 'Trusted server should have at least 3 transactions');

        // Verify transaction IDs match
        $storedIds = array_column($storedTransactions, 'transaction_id');
        foreach ($transactions as $tx) {
            $this->assertContains($tx->transactionId, $storedIds, "Transaction {$tx->transactionId} should be stored");
        }
    }

    /**
     * Test: Both nodes consume from shared RabbitMQ exchange
     */
    public function testBothNodesConsumeFromSharedExchange(): void
    {
        $shopNode = $this->getNode('shop');
        $trustedNode = $this->getNode('trusted-server');

        // Publish transaction from shop
        $transaction = $this->createTestTransaction(
            nodeId: 'shop',
            entityClass: UserStorage::class,
            entityId: 1,
            operation: 'save',
            fields: ['email' => 'test@example.com']
        );
        $shopNode->publishTransaction($transaction);

        // Both nodes consume (fanout exchange delivers to all queues)
        $shopConsumed = $shopNode->consumeOneTransaction(2.0);
        $trustedConsumed = $trustedNode->consumeOneTransaction(2.0);

        // Both should receive the message (fanout exchange)
        $this->assertTrue($shopConsumed, 'Shop node should consume its own transaction');
        $this->assertTrue($trustedConsumed, 'Trusted server should consume shop transaction');

        // Both nodes should have the transaction
        $shopTransactions = $shopNode->getBlockchainTransactions();
        $trustedTransactions = $trustedNode->getBlockchainTransactions();

        $this->assertCount(1, $shopTransactions, 'Shop node should have 1 transaction');
        $this->assertCount(1, $trustedTransactions, 'Trusted server should have 1 transaction');
        $this->assertSame($transaction->transactionId, $shopTransactions[0]['transaction_id']);
        $this->assertSame($transaction->transactionId, $trustedTransactions[0]['transaction_id']);
    }

    /**
     * Test: Transaction ordering is preserved across nodes
     */
    public function testTransactionOrdering(): void
    {
        $shopNode = $this->getNode('shop');
        $trustedNode = $this->getNode('trusted-server');

        // Publish transactions in order
        $transactions = [];
        for ($i = 1; $i <= 5; $i++) {
            $tx = $this->createTestTransaction(
                nodeId: 'shop',
                entityClass: UserStorage::class,
                entityId: $i,
                operation: 'save',
                fields: ['email' => "user{$i}@example.com"]
            );
            $shopNode->publishTransaction($tx);
            $transactions[] = $tx;
            
            // Small delay to ensure ordering
            usleep(10000); // 10ms
        }

        // Consume all transactions
        $trustedNode->consumeAllTransactions(3.0);

        // Verify transactions are stored in order
        $storedTransactions = $trustedNode->getBlockchainTransactions();
        $this->assertGreaterThanOrEqual(5, count($storedTransactions), 'Should have at least 5 transactions');

        // Check ordering (by created_at timestamp)
        for ($i = 0; $i < min(5, count($storedTransactions)); $i++) {
            $expectedTxId = $transactions[$i]->transactionId;
            $actualTxId = $storedTransactions[$i]['transaction_id'];
            $this->assertSame($expectedTxId, $actualTxId, "Transaction {$i} should be in correct order");
        }
    }

    /**
     * Test: Each node maintains separate blockchain database
     */
    public function testSeparateBlockchainDatabases(): void
    {
        $shopNode = $this->getNode('shop');
        $trustedNode = $this->getNode('trusted-server');

        // Initially both should be empty
        $shopTx = $shopNode->getBlockchainTransactions();
        $trustedTx = $trustedNode->getBlockchainTransactions();
        $this->assertCount(0, $shopTx, 'Shop blockchain should be empty initially');
        $this->assertCount(0, $trustedTx, 'Trusted blockchain should be empty initially');

        // Publish transaction
        $transaction = $this->createTestTransaction(
            nodeId: 'shop',
            entityClass: UserStorage::class,
            entityId: 1,
            operation: 'save',
            fields: ['email' => 'test@example.com']
        );
        $shopNode->publishTransaction($transaction);

        // Consume from both nodes
        $shopNode->consumeOneTransaction(1.0);
        $trustedNode->consumeOneTransaction(1.0);

        // Both should have the transaction now
        $shopTx = $shopNode->getBlockchainTransactions();
        $trustedTx = $trustedNode->getBlockchainTransactions();
        $this->assertCount(1, $shopTx, 'Shop blockchain should have 1 transaction');
        $this->assertCount(1, $trustedTx, 'Trusted blockchain should have 1 transaction');

        // But they are in separate databases
        $shopPdo = $shopNode->getPdo();
        $trustedPdo = $trustedNode->getPdo();
        
        // Verify they are different connections (different database names)
        $shopDb = $shopPdo->query("SELECT current_database()")->fetchColumn();
        $trustedDb = $trustedPdo->query("SELECT current_database()")->fetchColumn();
        $this->assertNotSame($shopDb, $trustedDb, 'Nodes should use separate databases');
    }

    /**
     * Create a test blockchain transaction
     */
    protected function createTestTransaction(
        string $nodeId,
        string $entityClass,
        int $entityId,
        string $operation,
        array $fields
    ): BlockchainTransaction {
        $generator = new TransactionIdGenerator();
        $timestamp = new \DateTimeImmutable();
        $nonce = base64_encode(random_bytes(16));

        // Generate transaction ID (includes nonce internally)
        $transactionId = $generator->generate(
            nodeId: $nodeId,
            entityClass: $entityClass,
            entityId: $entityId,
            operation: $operation,
            fields: $fields,
            timestamp: $timestamp
        );

        // Recreate ID with known nonce for consistency
        $transactionId = BlockchainTransaction::generateId(
            $nodeId,
            $entityClass,
            $entityId,
            $operation,
            $fields,
            $timestamp,
            $nonce
        );

        return new BlockchainTransaction(
            transactionId: $transactionId,
            nodeId: $nodeId,
            entityClass: $entityClass,
            entityId: $entityId,
            operation: $operation,
            fields: $fields,
            timestamp: $timestamp,
            nonce: $nonce,
            signature: null, // For testing, signature can be null
            keyVersion: 'v1',
        );
    }
}

