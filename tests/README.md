# Testing Guide

## 🚀 Running Tests

### Quick Start

Simply run:

```bash
bin/phpunit
```

**That's it!** The test infrastructure (Docker containers) will be automatically started.

### What Happens Automatically

When you run `bin/phpunit`, the bootstrap script (`tests/bootstrap.php`) automatically:

1. ✅ **Loads Composer autoloader**
2. ✅ **Starts Docker containers** (if not already running):
   - PostgreSQL for app tests (port 5433)
   - RabbitMQ for blockchain tests (port 5673)
   - PostgreSQL node1 for multi-node tests (port 5434)
   - PostgreSQL node2 for multi-node tests (port 5435)
3. ✅ **Waits for services to be ready**
4. ✅ **Verifies connections**

### Running Specific Tests

```bash
# Run all tests
bin/phpunit

# Run specific test file
bin/phpunit tests/Examples/Orm/Blockchain/MultiNodeBlockchainTest.php

# Run specific test method
bin/phpunit tests/Examples/Orm/Blockchain/MultiNodeBlockchainTest.php --filter testShopPublishesTrustedServerConsumes

# Run tests in specific directory
bin/phpunit tests/Examples/Orm/Blockchain/
```

### Environment Variables

You can control test behavior with environment variables:

```bash
# Skip automatic Docker setup (if you manage containers manually)
SKIP_DOCKER_SETUP=1 bin/phpunit

# Use SQLite instead of PostgreSQL (for faster unit tests)
TEST_WITH_SQLITE=1 bin/phpunit

# Set custom database port
DB_PORT=5434 bin/phpunit
```

## 🐳 Docker Infrastructure

### Services

| Service | Port | Purpose |
|---------|------|---------|
| `postgres-test` | 5433 | PostgreSQL for app tests |
| `rabbitmq-test` | 5673 | RabbitMQ for blockchain tests |
| `postgres-node1-blockchain` | 5434 | Blockchain DB for node1 |
| `postgres-node2-blockchain` | 5435 | Blockchain DB for node2 |

### Manual Docker Management

If you prefer to manage Docker containers manually:

```bash
# Start all services
docker-compose -f docker-compose.test.yml up -d

# Check status
docker ps | grep -E "(postgres|rabbitmq)"

# View logs
docker logs syntexa-postgres-test
docker logs syntexa-rabbitmq-test

# Stop all services
docker-compose -f docker-compose.test.yml down

# Stop and remove volumes (clean slate)
docker-compose -f docker-compose.test.yml down -v
```

## 📋 Test Structure

```
tests/
├── bootstrap.php                    # PHPUnit bootstrap (auto-starts Docker)
├── Examples/
│   ├── Orm/
│   │   ├── Blockchain/             # Blockchain tests
│   │   │   ├── MultiNodeTestCase.php
│   │   │   ├── MultiNodeBlockchainTest.php
│   │   │   └── ...
│   │   ├── PostgresTestHelper.php  # PostgreSQL helper
│   │   └── TestInfrastructureBootstrap.php  # Docker setup
│   └── ...
└── ...
```

## 🔧 Troubleshooting

### Problem: Docker containers not starting

**Symptoms:**
- Tests fail with connection errors
- Bootstrap shows warnings

**Solutions:**
1. Check Docker is running: `docker ps`
2. Check ports are not in use: `lsof -i :5433`
3. Try manual start: `docker-compose -f docker-compose.test.yml up -d`
4. Check logs: `docker logs syntexa-postgres-test`

### Problem: Tests are slow

**Solutions:**
1. Keep Docker containers running between test runs (they're reused automatically)
2. Use SQLite for unit tests: `TEST_WITH_SQLITE=1 bin/phpunit`
3. Run specific tests instead of all: `bin/phpunit tests/Examples/Orm/Blockchain/`

### Problem: Port conflicts

**Symptoms:**
- "address already in use" errors
- Containers fail to start

**Solutions:**
1. Check what's using the port: `lsof -i :5433`
2. Stop conflicting services
3. Use different ports via `.env` file:
   ```env
   DB_PORT=5434
   BLOCKCHAIN_RABBITMQ_PORT=5674
   ```

## 💡 Tips

1. **Keep containers running** - They're automatically reused, so keep them running between test runs for faster tests
2. **Use specific tests** - Run only the tests you need instead of all tests
3. **Check logs** - If tests fail, check Docker container logs
4. **Clean state** - If tests behave unexpectedly, restart containers: `docker-compose -f docker-compose.test.yml restart`

## 📚 Related Documentation

- [Blockchain Testing Guide](Examples/docs/Orm/Blockchain/README.md)
- [Multi-Node Testing Guide](Examples/docs/Orm/Blockchain/MULTI_NODE_TESTING.md)
- [PostgreSQL Setup](../docs/en/orm/POSTGRESQL_SETUP.md)
