# Blockchain Testing Strategy

## 📋 Overview

Blockchain testing has multiple levels of complexity due to:
- **Multi-node architecture** (BFT consensus)
- **RabbitMQ integration** (async messaging)
- **Separate blockchain database**
- **Merkle tree verification**
- **Distributed consensus**

## 🎯 Testing Levels

### 1. Unit Tests (Pure Logic)

**Files:**
- `MerkleTreeBuilderTest.php` - tests Merkle tree logic
- `TransactionIdGeneratorTest.php` - tests ID generation
- `BlockchainFieldExtractorTest.php` - tests field extraction

**Characteristics:**
- ✅ No database required
- ✅ No RabbitMQ required
- ✅ Fast (< 1ms each)
- ✅ Deterministic

**Example:**
```php
public function testMerkleTreeDeterministic(): void
{
    $builder = new MerkleTreeBuilder();
    $tx1 = $this->createTransaction(...);
    $tx2 = $this->createTransaction(...);
    
    $root1 = $builder->buildTree([$tx1, $tx2]);
    $root2 = $builder->buildTree([$tx1, $tx2]);
    
    $this->assertSame($root1, $root2); // Deterministic
}
```

### 2. Integration Tests (DB + Mock RabbitMQ)

**Files:**
- `BlockchainIntegrationTest.php` - tests EntityManager → Storage flow
- `BlockchainStorageTest.php` - tests database operations

**Characteristics:**
- ✅ Uses real blockchain database (PostgreSQL)
- ✅ Mock RabbitMQ (InMemoryTransport or in-memory array)
- ✅ Tests full flow: `save()` → `BlockchainPublisher` → `BlockchainStorage`

**Example:**
```php
public function testSaveCreatesBlockchainTransaction(): void
{
    $user = $userRepo->create();
    $user->setEmail('test@example.com');
    $savedUser = $userRepo->save($user);
    
    // Check blockchain storage
    $transactions = $this->getBlockchainTransactions();
    $this->assertCount(1, $transactions);
    $this->assertSame('save', $transactions[0]['operation']);
}
```

### 3. E2E Tests (Real RabbitMQ)

**Files:**
- `BlockchainE2ETest.php` - tests full flow with real RabbitMQ

**Characteristics:**
- ✅ Uses real RabbitMQ (Docker container)
- ✅ Tests `EntityManager.save()` → RabbitMQ → Consumer → Storage
- ⚠️ Requires Docker and RabbitMQ container

**Example:**
```php
public function testEndToEndFlow(): void
{
    // 1. Save entity (publishes to RabbitMQ)
    $user = $userRepo->save($user);
    
    // 2. Run consumer (reads from RabbitMQ)
    $this->runConsumerOnce();
    
    // 3. Verify transaction in blockchain DB
    $tx = $this->getTransaction($user->getId());
    $this->assertNotNull($tx);
}
```

### 4. Multi-Node Tests (BFT Consensus)

**Files:**
- `BlockchainBFTTest.php` - tests BFT consensus with multiple nodes

**Characteristics:**
- ✅ Simulates multiple nodes (3+ for BFT)
- ✅ Tests block proposal, voting, finalization
- ✅ Tests fork resolution
- ⚠️ Complex tests, require significant time

**Example:**
```php
public function testBFTConsensus(): void
{
    // Create 3 nodes
    $node1 = $this->createNode('node1');
    $node2 = $this->createNode('node2');
    $node3 = $this->createNode('node3');
    
    // Node1 proposes block
    $block = $node1->proposeBlock();
    
    // All nodes vote
    $votes = [
        $node1->vote($block),
        $node2->vote($block),
        $node3->vote($block),
    ];
    
    // Should finalize (2/3+ votes)
    $this->assertTrue($node1->isFinalized($block));
}
```

## 🛠️ Test Infrastructure

### Base Test Case: `BlockchainTestCase`

**Provides:**
- ✅ Separate blockchain database (isolated from app database)
- ✅ Mock RabbitMQ (in-memory queue)
- ✅ Helper methods for blockchain operations
- ✅ Automatic cleanup

**Usage:**
```php
class MyBlockchainTest extends BlockchainTestCase
{
    protected function needsBlockchainDb(): bool
    {
        return true; // or false for unit tests
    }
    
    public function testSomething(): void
    {
        // Use $this->blockchainStorage
        // Use $this->config
        // Use $this->mockRabbitMQPublish()
    }
}
```

### Test Database Setup

**Structure:**
```
syntexa_test              # App database (from OrmExampleTestCase)
syntexa_test_blockchain   # Blockchain database (separate)
```

**Benefits:**
- ✅ Complete test isolation
- ✅ Can clean blockchain database independently
- ✅ Can test without affecting app database

### Mock RabbitMQ

**For unit/integration tests:**
```php
// In BlockchainTestCase
protected function mockRabbitMQPublish(string $exchange, string $payload): void
{
    self::$mockQueue[$exchange][] = $payload;
}

protected function getMockQueueMessages(string $exchange): array
{
    return self::$mockQueue[$exchange] ?? [];
}
```

**For E2E tests:**
- Use real RabbitMQ in Docker
- `docker-compose.test.yml` may contain RabbitMQ service

## 📝 Test Examples

### Example 1: Unit Test (Merkle Tree)

```php
class MerkleTreeBuilderTest extends BlockchainTestCase
{
    protected function needsBlockchainDb(): bool
    {
        return false; // No DB needed
    }
    
    public function testDeterministicTree(): void
    {
        $builder = new MerkleTreeBuilder();
        $tx1 = $this->createTransaction(...);
        $tx2 = $this->createTransaction(...);
        
        $root1 = $builder->buildTree([$tx1, $tx2]);
        $root2 = $builder->buildTree([$tx1, $tx2]);
        
        $this->assertSame($root1, $root2);
    }
}
```

### Example 2: Integration Test (EntityManager → Storage)

```php
class BlockchainIntegrationTest extends OrmExampleTestCase
{
    public function testSaveCreatesTransaction(): void
    {
        // Save entity
        $user = $userRepo->save($user);
        
        // Check blockchain storage
        $tx = $this->getBlockchainTransaction($user->getId());
        $this->assertNotNull($tx);
        $this->assertSame('save', $tx['operation']);
    }
}
```

### Example 3: E2E Test (RabbitMQ Flow)

```php
class BlockchainE2ETest extends BlockchainTestCase
{
    public function testRabbitMQFlow(): void
    {
        // 1. Save entity (publishes to RabbitMQ)
        $user = $userRepo->save($user);
        
        // 2. Consume from RabbitMQ
        $consumer = new BlockchainConsumer($this->config);
        $consumer->consumeOnce();
        
        // 3. Verify in blockchain DB
        $tx = $this->getBlockchainTransaction($user->getId());
        $this->assertNotNull($tx);
    }
}
```

## 🚀 Running Tests

### Unit Tests (Fast)
```bash
# Only unit tests (no DB, no RabbitMQ)
php vendor/bin/phpunit tests/Examples/Orm/Blockchain/MerkleTreeBuilderTest.php
```

### Integration Tests (Medium)
```bash
# Integration tests (DB, mock RabbitMQ)
php vendor/bin/phpunit tests/Examples/Orm/Blockchain/BlockchainIntegrationTest.php
```

### E2E Tests (Slow, requires RabbitMQ)
```bash
# Start RabbitMQ
docker-compose -f docker-compose.test.yml up -d rabbitmq

# Run E2E tests
php vendor/bin/phpunit tests/Examples/Orm/Blockchain/BlockchainE2ETest.php

# Stop RabbitMQ
docker-compose -f docker-compose.test.yml down
```

### All Blockchain Tests
```bash
php vendor/bin/phpunit tests/Examples/Orm/Blockchain/
```

## 🎯 Test Coverage Goals

- ✅ **Unit tests**: 100% for MerkleTreeBuilder, TransactionIdGenerator
- ✅ **Integration tests**: EntityManager → Storage flow
- ✅ **E2E tests**: RabbitMQ publish → consume → storage
- ⏳ **BFT tests**: When BFT consensus is implemented

## 💡 Tips

1. **Mock RabbitMQ for fast tests** - use `mockRabbitMQPublish()` instead of real RabbitMQ
2. **Separate DB for blockchain** - don't mix app database and blockchain database in tests
3. **Cleanup after tests** - always clean blockchain database in `tearDown()`
4. **Deterministic tests** - use fixed timestamps for deterministic results
5. **Small blocks for tests** - use `blockSize: 10` instead of 100 for faster tests
