# Multi-Node Blockchain Testing Guide

## 🎯 Overview

Multi-node blockchain tests simulate a distributed blockchain network with multiple nodes, each having:
- **Own PostgreSQL database** (isolated)
- **Shared RabbitMQ exchange** (fanout)
- **Independent blockchain storage**

This allows testing real-world scenarios like:
- Shop node publishing transactions
- Trusted server consuming and validating
- Multiple nodes maintaining synchronized blockchain state

## 🚀 Quick Start

### Automatic Setup (Recommended)

**Infrastructure is automatically started when you run tests:**

```bash
# Just run tests - Docker containers will be started automatically!
bin/phpunit tests/Examples/Orm/Blockchain/MultiNodeBlockchainTest.php

# Or use vendor/bin/phpunit directly
vendor/bin/phpunit tests/Examples/Orm/Blockchain/MultiNodeBlockchainTest.php
```

The bootstrap script (`tests/bootstrap.php`) automatically:
- ✅ Starts all required Docker containers (PostgreSQL, RabbitMQ)
- ✅ Waits for services to be ready
- ✅ Verifies connections

### Manual Setup (Optional)

If you prefer to manage Docker containers manually:

```bash
# 1. Start infrastructure manually
docker-compose -f docker-compose.test.yml up -d

# 2. Wait for services (about 5-10 seconds)
sleep 5

# 3. Run tests (will skip Docker setup)
SKIP_DOCKER_SETUP=1 bin/phpunit tests/Examples/Orm/Blockchain/MultiNodeBlockchainTest.php

# 4. Stop infrastructure when done
docker-compose -f docker-compose.test.yml down
```

### Skip Automatic Docker Setup

If you want to skip automatic Docker setup:

```bash
SKIP_DOCKER_SETUP=1 bin/phpunit tests/Examples/Orm/Blockchain/MultiNodeBlockchainTest.php
```

## 📋 Infrastructure Details

### Services

| Service | Port | Purpose |
|---------|------|---------|
| `rabbitmq-test` | 5673 | Shared RabbitMQ message broker |
| `postgres-node1-blockchain` | 5434 | Blockchain DB for node1/shop |
| `postgres-node2-blockchain` | 5435 | Blockchain DB for node2/trusted-server |

### Environment Variables

You can override defaults via environment variables:

```bash
export BLOCKCHAIN_RABBITMQ_HOST=localhost
export BLOCKCHAIN_RABBITMQ_PORT=5673
export BLOCKCHAIN_RABBITMQ_USER=test
export BLOCKCHAIN_RABBITMQ_PASSWORD=test
export BLOCKCHAIN_RABBITMQ_EXCHANGE=syntexa_blockchain_test
```

## 🧪 Writing Multi-Node Tests

### Basic Structure

```php
class MyMultiNodeTest extends MultiNodeTestCase
{
    // Define number of nodes
    protected int $nodeCount = 2;
    
    // Define node IDs
    protected array $nodeIds = ['shop', 'trusted-server'];

    public function testMyScenario(): void
    {
        // Get node simulators
        $shopNode = $this->getNode('shop');
        $trustedNode = $this->getNode('trusted-server');

        // Create and publish transaction
        $tx = $this->createTestTransaction(
            nodeId: 'shop',
            entityClass: UserStorage::class,
            entityId: 1,
            operation: 'save',
            fields: ['email' => 'test@example.com']
        );
        $shopNode->publishTransaction($tx);

        // Consume from all nodes
        $this->consumeFromAllNodes(2.0);

        // Assert all nodes have transaction
        $this->assertAllNodesHaveTransaction($tx->transactionId);
    }
}
```

### Available Helper Methods

**From MultiNodeTestCase:**
- `getNode($nodeId)` - Get node simulator
- `consumeFromAllNodes($timeout)` - Consume from all nodes
- `waitForAllNodesToConsume($expectedCount, $timeout)` - Wait until all consumed
- `assertAllNodesHaveSameTransactionCount($expectedCount)` - Assert same count
- `assertAllNodesHaveTransaction($transactionId)` - Assert transaction exists
- `getTransactionCounts()` - Get counts for all nodes

**From NodeSimulator:**
- `publishTransaction($transaction)` - Publish to RabbitMQ
- `consumeOneTransaction($timeout)` - Consume one (non-blocking)
- `consumeAllTransactions($timeout)` - Consume all available
- `getBlockchainTransactions()` - Get all stored transactions
- `getLastFinalizedBlockHash()` - Get last finalized block hash
- `getLastFinalizedHeight()` - Get last finalized height

## 🔍 Example Test Scenarios

### Scenario 1: Shop Publishes, Trusted Server Consumes

```php
public function testShopPublishesTrustedConsumes(): void
{
    $shopNode = $this->getNode('shop');
    $trustedNode = $this->getNode('trusted-server');

    // Shop publishes
    $tx = $this->createTestTransaction(...);
    $shopNode->publishTransaction($tx);

    // Trusted consumes
    $consumed = $trustedNode->consumeOneTransaction(2.0);
    $this->assertTrue($consumed);

    // Verify stored
    $transactions = $trustedNode->getBlockchainTransactions();
    $this->assertCount(1, $transactions);
}
```

### Scenario 2: Multiple Transactions, All Nodes Consume

```php
public function testMultipleTransactionsAllNodesConsume(): void
{
    $shopNode = $this->getNode('shop');
    
    // Publish 5 transactions
    for ($i = 1; $i <= 5; $i++) {
        $tx = $this->createTestTransaction(...);
        $shopNode->publishTransaction($tx);
    }

    // All nodes consume
    $consumed = $this->consumeFromAllNodes(3.0);
    
    // Verify all nodes have same count
    $this->assertAllNodesHaveSameTransactionCount(5);
}
```

### Scenario 3: Transaction Ordering

```php
public function testTransactionOrdering(): void
{
    $shopNode = $this->getNode('shop');
    $trustedNode = $this->getNode('trusted-server');

    // Publish in order
    $transactions = [];
    for ($i = 1; $i <= 5; $i++) {
        $tx = $this->createTestTransaction(...);
        $shopNode->publishTransaction($tx);
        $transactions[] = $tx;
        usleep(10000); // Small delay
    }

    // Consume all
    $trustedNode->consumeAllTransactions(3.0);

    // Verify order
    $stored = $trustedNode->getBlockchainTransactions();
    for ($i = 0; $i < 5; $i++) {
        $this->assertSame($transactions[$i]->transactionId, $stored[$i]['transaction_id']);
    }
}
```

## 🐛 Troubleshooting

### Problem: RabbitMQ Connection Fails

**Symptoms:**
```
AMQPConnectionException: Could not connect to RabbitMQ
```

**Solutions:**
1. Check if RabbitMQ is running: `docker ps | grep rabbitmq`
2. Verify port 5673 is not in use: `lsof -i :5673`
3. Check RabbitMQ logs: `docker logs syntexa-rabbitmq-test`
4. Try restarting: `docker-compose -f docker-compose.test.yml restart rabbitmq-test`

### Problem: Database Connection Fails

**Symptoms:**
```
PDOException: Could not connect to database
```

**Solutions:**
1. Check if PostgreSQL containers are running: `docker ps | grep postgres-node`
2. Verify ports 5434 and 5435 are not in use
3. Check database logs: `docker logs syntexa-postgres-node1-blockchain`
4. Try recreating databases: `docker-compose -f docker-compose.test.yml up -d --force-recreate`

### Problem: Transactions Not Consumed

**Symptoms:**
- `consumeOneTransaction()` returns `false`
- No transactions in `getBlockchainTransactions()`

**Solutions:**
1. Increase timeout: `consumeOneTransaction(5.0)` instead of `2.0`
2. Check RabbitMQ management UI: http://localhost:15673 (guest/guest)
3. Verify exchange exists: `syntexa_blockchain_test`
4. Verify queues are bound: Each node should have `blockchain.{nodeId}` queue
5. Check for errors in RabbitMQ logs

### Problem: Tests Are Slow

**Solutions:**
1. Use smaller `blockSize` in config (default: 10)
2. Reduce `consensusTimeout` (default: 10 seconds)
3. Use `consumeOneTransaction()` instead of `consumeAllTransactions()` when possible
4. Reduce number of transactions in tests
5. Use `waitForAllNodesToConsume()` with shorter timeout

## 📊 Performance Tips

1. **Use tmpfs for databases** - Already configured in docker-compose.test.yml
2. **Cleanup after tests** - Automatic via `tearDown()`
3. **Reuse infrastructure** - Start once, run multiple tests
4. **Parallel test execution** - Can run multiple test classes in parallel (with separate DBs)

## 🔗 Related Files

- `MultiNodeTestCase.php` - Base test case
- `NodeSimulator.php` - Node simulator implementation
- `MultiNodeBlockchainTest.php` - Example tests
- `docker-compose.test.yml` - Infrastructure definition
- `README.md` - General blockchain testing guide

