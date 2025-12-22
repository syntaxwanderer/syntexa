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
- `MultiNodeBlockchainTest.php` - tests multi-node blockchain with RabbitMQ
- `MultiNodeTestCase.php` - base test case for multi-node tests
- `NodeSimulator.php` - simulates a blockchain node

**Characteristics:**
- ✅ Simulates multiple nodes (each with own database)
- ✅ Shared RabbitMQ exchange (fanout)
- ✅ Tests transaction publishing and consumption
- ✅ Tests transaction ordering and consistency
- ⚠️ Requires Docker Compose (RabbitMQ + multiple PostgreSQL databases)

**Infrastructure:**
- **RabbitMQ**: Shared message broker (port 5673)
- **PostgreSQL Node 1**: Shop node blockchain DB (port 5434)
- **PostgreSQL Node 2**: Trusted server blockchain DB (port 5435)

**Example:**
```php
class MyMultiNodeTest extends MultiNodeTestCase
{
    protected int $nodeCount = 2;
    protected array $nodeIds = ['shop', 'trusted-server'];

    public function testMultiNodeFlow(): void
    {
        $shopNode = $this->getNode('shop');
        $trustedNode = $this->getNode('trusted-server');

        // Shop publishes transaction
        $tx = $this->createTestTransaction(...);
        $shopNode->publishTransaction($tx);

        // Trusted server consumes
        $trustedNode->consumeOneTransaction(2.0);

        // Verify both nodes have transaction
        $this->assertAllNodesHaveTransaction($tx->transactionId);
    }
}
```

**How It Works:**
1. `MultiNodeTestCase` creates multiple `NodeSimulator` instances
2. Each node has its own PostgreSQL database (isolated)
3. All nodes connect to the same RabbitMQ exchange (fanout)
4. When a node publishes, all nodes receive the message (fanout)
5. Each node stores transactions in its own blockchain database

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

### Multi-Node Tests (Requires RabbitMQ + Multiple DBs)
```bash
# Start infrastructure (RabbitMQ + multiple PostgreSQL databases)
docker-compose -f docker-compose.test.yml up -d

# Wait for services to be ready
sleep 5

# Run multi-node tests
php vendor/bin/phpunit tests/Examples/Orm/Blockchain/MultiNodeBlockchainTest.php

# Stop infrastructure
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
6. **Multi-node tests** - use `MultiNodeTestCase` for testing distributed blockchain scenarios

## 🔧 Multi-Node Testing Setup

### Prerequisites

1. **Docker Compose** - Required for RabbitMQ and multiple PostgreSQL databases
2. **ext-amqp** PHP extension - Required for RabbitMQ connectivity
3. **PostgreSQL** - Multiple databases for different nodes

### Infrastructure Setup

The `docker-compose.test.yml` file provides:
- **RabbitMQ** (port 5673) - Shared message broker
- **PostgreSQL Node 1** (port 5434) - Blockchain DB for first node
- **PostgreSQL Node 2** (port 5435) - Blockchain DB for second node

### Creating Multi-Node Tests

**Step 1: Extend MultiNodeTestCase**

```php
class MyMultiNodeTest extends MultiNodeTestCase
{
    protected int $nodeCount = 2;
    protected array $nodeIds = ['shop', 'trusted-server'];
    
    public function testMyScenario(): void
    {
        // Access nodes via $this->getNode('shop')
        // Or $this->nodes['shop']
    }
}
```

**Step 2: Use Node Simulators**

```php
$shopNode = $this->getNode('shop');
$trustedNode = $this->getNode('trusted-server');

// Publish transaction
$tx = $this->createTestTransaction(...);
$shopNode->publishTransaction($tx);

// Consume from all nodes
$this->consumeFromAllNodes(2.0);

// Assert all nodes have transaction
$this->assertAllNodesHaveTransaction($tx->transactionId);
```

**Step 3: Helper Methods**

- `consumeFromAllNodes($timeout)` - Consume transactions from all nodes
- `waitForAllNodesToConsume($expectedCount, $timeout)` - Wait until all nodes consumed expected count
- `assertAllNodesHaveSameTransactionCount($expectedCount)` - Assert all nodes have same count
- `assertAllNodesHaveTransaction($transactionId)` - Assert transaction exists in all nodes
- `getTransactionCounts()` - Get transaction counts for all nodes

### Node Simulator API

**NodeSimulator** provides:
- `publishTransaction($transaction)` - Publish to RabbitMQ
- `consumeOneTransaction($timeout)` - Consume one transaction (non-blocking)
- `consumeAllTransactions($timeout)` - Consume all available transactions
- `getBlockchainTransactions()` - Get all stored transactions
- `getLastFinalizedBlockHash()` - Get last finalized block hash
- `getLastFinalizedHeight()` - Get last finalized block height
- `cleanup()` - Clean blockchain database
- `close()` - Close RabbitMQ connection

### Example: Shop + Trusted Server Scenario

```php
class ShopTrustedServerTest extends MultiNodeTestCase
{
    protected int $nodeCount = 2;
    protected array $nodeIds = ['shop', 'trusted-server'];

    public function testShopPublishesTrustedConsumes(): void
    {
        $shopNode = $this->getNode('shop');
        $trustedNode = $this->getNode('trusted-server');

        // Shop creates and publishes transaction
        $tx = $this->createTestTransaction(
            nodeId: 'shop',
            entityClass: UserStorage::class,
            entityId: 1,
            operation: 'save',
            fields: ['email' => 'test@example.com']
        );
        $shopNode->publishTransaction($tx);

        // Trusted server consumes
        $consumed = $trustedNode->consumeOneTransaction(2.0);
        $this->assertTrue($consumed);

        // Verify transaction stored
        $transactions = $trustedNode->getBlockchainTransactions();
        $this->assertCount(1, $transactions);
        $this->assertSame($tx->transactionId, $transactions[0]['transaction_id']);
    }
}
```

### Troubleshooting

**Problem: RabbitMQ connection fails**
- Check if RabbitMQ container is running: `docker ps | grep rabbitmq`
- Verify port 5673 is not in use
- Check RabbitMQ logs: `docker logs syntexa-rabbitmq-test`

**Problem: Database connection fails**
- Check if PostgreSQL containers are running: `docker ps | grep postgres`
- Verify ports 5434 and 5435 are not in use
- Check database logs: `docker logs syntexa-postgres-node1-blockchain`

**Problem: Transactions not consumed**
- Verify RabbitMQ exchange is created (check RabbitMQ management UI)
- Check queue bindings (each node should have its own queue)
- Increase timeout in `consumeOneTransaction()` or `consumeAllTransactions()`

**Problem: Tests are slow**
- Use smaller `blockSize` in config (default: 10 for tests)
- Reduce `consensusTimeout` (default: 10 seconds)
- Use `consumeOneTransaction()` instead of `consumeAllTransactions()` when possible
