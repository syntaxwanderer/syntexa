# Docker Configuration for Syntexa Framework

This document describes the Docker setup for running Syntexa Framework in containers.

## Overview

The project uses Docker Compose to orchestrate multiple services:

### Production Setup (`docker-compose.yml`)

- **postgres-app**: Main application database
- **rabbitmq**: RabbitMQ message broker for blockchain (production vhost: `/production`)
- **postgres-node1-blockchain**: Blockchain database for node1
- **postgres-node2-blockchain**: Blockchain database for node2
- **php-app**: Main PHP application (Swoole server)
- **php-node1**: Blockchain consumer for node1
- **php-node2**: Blockchain consumer for node2

### Test Setup (`docker-compose.test.yml`)

- **postgres-test**: Test application database
- **rabbitmq-test**: RabbitMQ for tests (test vhost: `/test`)
- **postgres-node1-blockchain**: Test blockchain database for node1
- **postgres-node2-blockchain**: Test blockchain database for node2
- **php-test**: PHP container for running tests

## Quick Start

### 1. Setup Environment

```bash
# Copy example environment file
cp env.example .env

# Edit .env with your configuration
nano .env
```

### 2. Start Production Services

```bash
# Build and start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Stop services
docker-compose down
```

### 3. Run Tests

```bash
# Start test infrastructure
docker-compose -f docker-compose.test.yml up -d

# Run tests in container
docker-compose -f docker-compose.test.yml run --rm php-test

# Or run tests locally (requires test containers running)
php vendor/bin/phpunit
```

## Database Separation

### Production Databases

- **Main App DB**: `postgres-app` (port 5432)
- **Node1 Blockchain DB**: `postgres-node1-blockchain` (port 5433)
- **Node2 Blockchain DB**: `postgres-node2-blockchain` (port 5434)

### Test Databases

- **Test App DB**: `postgres-test` (port 5436)
- **Node1 Test Blockchain DB**: `postgres-node1-blockchain` (port 5434)
- **Node2 Test Blockchain DB**: `postgres-node2-blockchain` (port 5435)

**Important**: Test databases use different ports and are completely isolated from production.

## RabbitMQ Queue Separation

### Production Queues

- **VHost**: `/production`
- **Exchange**: `syntexa_blockchain` (configurable via `BLOCKCHAIN_RABBITMQ_EXCHANGE`)
- **Port**: 5672
- **Management UI**: http://localhost:15672

### Test Queues

- **VHost**: `/test`
- **Exchange**: `syntexa_blockchain_test` (configurable)
- **Port**: 5673
- **Management UI**: http://localhost:15673

**Important**: Tests use a separate vhost (`/test`) to ensure complete isolation from production queues.

## Blockchain Multi-Node Setup

The project supports running multiple blockchain nodes:

### Node1 (Main Application)

- Runs Swoole server on port 8080
- Uses `postgres-node1-blockchain` database
- Publishes transactions to RabbitMQ
- Node ID: `node1`

### Node2 (Separate Instance)

- Runs blockchain consumer only
- Uses `postgres-node2-blockchain` database
- Consumes transactions from RabbitMQ
- Node ID: `node2`

### Configuration

Set in `.env`:

```env
BLOCKCHAIN_NODE_ID=node1
BLOCKCHAIN_PARTICIPANTS=node1,node2
BLOCKCHAIN_RABBITMQ_VHOST=/production
BLOCKCHAIN_RABBITMQ_EXCHANGE=syntexa_blockchain
```

## Dockerfiles

### Dockerfile

Production PHP container with:
- PHP 8.4 CLI
- Swoole extension
- PostgreSQL extension
- AMQP extension (RabbitMQ)
- Composer
- Production dependencies only

### Dockerfile.test

Test PHP container with:
- Same as Dockerfile
- Development dependencies included
- Optimized for running tests

## Volumes

### Production Volumes

- `postgres-app-data`: Persistent storage for main database
- `postgres-node1-blockchain-data`: Persistent storage for node1 blockchain
- `postgres-node2-blockchain-data`: Persistent storage for node2 blockchain
- `rabbitmq-data`: Persistent storage for RabbitMQ

### Test Volumes

- Test databases use `tmpfs` (in-memory) for faster tests
- Data is lost when containers stop
- RabbitMQ also uses `tmpfs` for tests

## Environment Variables

See `env.example` for all available environment variables.

### Key Variables

**Database:**
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`

**Blockchain:**
- `BLOCKCHAIN_ENABLED`: Enable/disable blockchain
- `BLOCKCHAIN_DB_HOST`, `BLOCKCHAIN_DB_NAME`, etc.
- `BLOCKCHAIN_RABBITMQ_*`: RabbitMQ configuration
- `BLOCKCHAIN_NODE_ID`: Unique node identifier
- `BLOCKCHAIN_PARTICIPANTS`: Comma-separated list of all nodes

**Swoole:**
- `SWOOLE_HOST`, `SWOOLE_PORT`, `SWOOLE_WORKER_NUM`, etc.

## Local Development

### Using docker-compose.override.yml

Create `docker-compose.override.yml` (git-ignored) to override settings:

```yaml
services:
  php-app:
    volumes:
      - .:/var/www/html:cached
    # Add development tools, Xdebug, etc.
```

### Running Locally (without Docker)

You can run the application locally while using Docker for databases:

```bash
# Start only databases and RabbitMQ
docker-compose up -d postgres-app rabbitmq postgres-node1-blockchain postgres-node2-blockchain

# Run application locally
php server.php
```

## Troubleshooting

### Port Conflicts

If ports are already in use, modify ports in `docker-compose.yml` or use `docker-compose.override.yml`.

### RabbitMQ VHost Not Found

RabbitMQ automatically creates vhosts on first connection. If you need to create manually:

```bash
docker exec -it syntexa-rabbitmq rabbitmqctl add_vhost /production
docker exec -it syntexa-rabbitmq rabbitmqctl add_vhost /test
```

### Database Connection Issues

Check container health:

```bash
docker-compose ps
docker-compose logs postgres-app
```

### Test Containers Not Starting

Ensure test containers are healthy before running tests:

```bash
docker-compose -f docker-compose.test.yml ps
docker-compose -f docker-compose.test.yml logs
```

## Best Practices

1. **Never run tests against production databases** - Tests use separate databases
2. **Use separate RabbitMQ vhosts** - Production (`/production`) vs Tests (`/test`)
3. **Use volumes for production** - Persistent storage for production data
4. **Use tmpfs for tests** - Faster tests, data is ephemeral
5. **Keep .env.local git-ignored** - Store local overrides there
6. **Use docker-compose.override.yml** - For local development customizations

## Commands Reference

```bash
# Production
docker-compose up -d              # Start all services
docker-compose down               # Stop all services
docker-compose logs -f php-app    # View application logs
docker-compose restart php-app     # Restart application

# Tests
docker-compose -f docker-compose.test.yml up -d    # Start test infrastructure
docker-compose -f docker-compose.test.yml down     # Stop test infrastructure
docker-compose -f docker-compose.test.yml run --rm php-test  # Run tests

# Database
docker-compose exec postgres-app psql -U postgres -d syntexa  # Connect to DB
docker-compose exec postgres-app pg_dump -U postgres syntexa > backup.sql  # Backup

# RabbitMQ
docker-compose exec rabbitmq rabbitmqctl list_vhosts  # List vhosts
docker-compose exec rabbitmq rabbitmqctl list_queues -p /production  # List queues
```

