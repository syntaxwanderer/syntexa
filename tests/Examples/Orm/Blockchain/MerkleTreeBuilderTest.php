<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm\Blockchain;

use Syntexa\Orm\Blockchain\BlockchainTransaction;
use Syntexa\Orm\Blockchain\MerkleTreeBuilder;

/**
 * Unit tests for MerkleTreeBuilder
 * 
 * Tests:
 * - Deterministic tree building (same transactions → same root)
 * - Empty tree handling
 * - Tree verification
 * - Odd number of transactions
 */
class MerkleTreeBuilderTest extends BlockchainTestCase
{
    protected function needsBlockchainDb(): bool
    {
        return false; // Unit test, no DB needed
    }

    public function testEmptyTree(): void
    {
        $builder = new MerkleTreeBuilder();
        $root = $builder->buildTree([]);
        
        // Empty tree should return hash of empty string
        $expected = hash('sha256', '');
        $this->assertSame($expected, $root);
    }

    public function testSingleTransaction(): void
    {
        $builder = new MerkleTreeBuilder();
        
        $tx = $this->createTestTransaction('entity1', 1, 'save', ['field1' => 'value1']);
        $root = $builder->buildTree([$tx]);
        
        // Single transaction: root = hash of transaction JSON
        $expected = hash('sha256', $tx->toJson());
        $this->assertSame($expected, $root);
    }

    public function testTwoTransactions(): void
    {
        $builder = new MerkleTreeBuilder();
        
        $tx1 = $this->createTestTransaction('entity1', 1, 'save', ['field1' => 'value1']);
        $tx2 = $this->createTestTransaction('entity2', 2, 'save', ['field2' => 'value2']);
        
        $root = $builder->buildTree([$tx1, $tx2]);
        
        // Two transactions: root = hash(hash(tx1) + hash(tx2))
        $hash1 = hash('sha256', $tx1->toJson());
        $hash2 = hash('sha256', $tx2->toJson());
        $expected = hash('sha256', $hash1 . $hash2);
        
        $this->assertSame($expected, $root);
    }

    public function testOddNumberOfTransactions(): void
    {
        $builder = new MerkleTreeBuilder();
        
        $tx1 = $this->createTestTransaction('entity1', 1, 'save', ['field1' => 'value1']);
        $tx2 = $this->createTestTransaction('entity2', 2, 'save', ['field2' => 'value2']);
        $tx3 = $this->createTestTransaction('entity3', 3, 'save', ['field3' => 'value3']);
        
        $root = $builder->buildTree([$tx1, $tx2, $tx3]);
        
        // Odd number: last hash is duplicated
        // Level 1: hash(tx1), hash(tx2), hash(tx3), hash(tx3) [duplicated]
        // Level 2: hash(hash(tx1) + hash(tx2)), hash(hash(tx3) + hash(tx3))
        // Root: hash(level2[0] + level2[1])
        
        $this->assertNotEmpty($root);
        $this->assertSame(64, strlen($root)); // SHA-256 hex = 64 chars
    }

    public function testDeterministicTree(): void
    {
        $builder = new MerkleTreeBuilder();
        
        $tx1 = $this->createTestTransaction('entity1', 1, 'save', ['field1' => 'value1']);
        $tx2 = $this->createTestTransaction('entity2', 2, 'save', ['field2' => 'value2']);
        $tx3 = $this->createTestTransaction('entity3', 3, 'save', ['field3' => 'value3']);
        
        $transactions = [$tx1, $tx2, $tx3];
        
        // Build tree twice - should get same root
        $root1 = $builder->buildTree($transactions);
        $root2 = $builder->buildTree($transactions);
        
        $this->assertSame($root1, $root2);
    }

    public function testVerifyRoot(): void
    {
        $builder = new MerkleTreeBuilder();
        
        $tx1 = $this->createTestTransaction('entity1', 1, 'save', ['field1' => 'value1']);
        $tx2 = $this->createTestTransaction('entity2', 2, 'save', ['field2' => 'value2']);
        
        $transactions = [$tx1, $tx2];
        $root = $builder->buildTree($transactions);
        
        // Verify correct root
        $this->assertTrue($builder->verifyRoot($transactions, $root));
        
        // Verify wrong root
        $this->assertFalse($builder->verifyRoot($transactions, 'wrong_root_hash'));
    }

    public function testOrderMatters(): void
    {
        $builder = new MerkleTreeBuilder();
        
        $tx1 = $this->createTestTransaction('entity1', 1, 'save', ['field1' => 'value1']);
        $tx2 = $this->createTestTransaction('entity2', 2, 'save', ['field2' => 'value2']);
        
        $root1 = $builder->buildTree([$tx1, $tx2]);
        $root2 = $builder->buildTree([$tx2, $tx1]);
        
        // Different order should produce different root
        $this->assertNotSame($root1, $root2);
    }

    /**
     * Create test transaction
     */
    private function createTestTransaction(
        string $entityClass,
        int $entityId,
        string $operation,
        array $fields
    ): BlockchainTransaction {
        return new BlockchainTransaction(
            transactionId: hash('sha256', $entityClass . $entityId . $operation),
            nodeId: 'test-node',
            entityClass: $entityClass,
            entityId: $entityId,
            operation: $operation,
            fields: $fields,
            timestamp: new \DateTimeImmutable(),
            nonce: base64_encode(random_bytes(32)),
        );
    }
}

