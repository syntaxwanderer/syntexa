# ORM Examples Tests

These tests serve as **executable examples** of how to use Syntexa ORM. They demonstrate:

- Basic CRUD operations (`BasicCrudTest.php`)
- Domain projection and selective mapping (`DomainProjectionTest.php`)
- Query builder with joins (`QueryBuilderJoinsTest.php`)
- Entity relationships: OneToOne, OneToMany, ManyToOne, ManyToMany (`RelationshipsTest.php`)
- Automatic relationship loading: Lazy loading and domain projection (`RelationshipLoadingTest.php`)

## Running Tests

### Default: PostgreSQL (Recommended)

```bash
# Run all ORM example tests (uses PostgreSQL from .env)
./bin/phpunit tests/Examples/Orm/

# Run specific test
./bin/phpunit tests/Examples/Orm/BasicCrudTest.php
```

Tests use **PostgreSQL database** by default:
1. Automatically reads configuration from `.env` file
2. Automatically starts PostgreSQL container (`syntexa-postgres-test`) if needed
3. Waits for database to be ready
4. Runs all tests against PostgreSQL
5. Cleans up schema between tests

### Fallback: SQLite (Fast, No Setup)

To use SQLite instead of PostgreSQL:

```bash
# Use SQLite in-memory database
TEST_WITH_SQLITE=1 ./bin/phpunit tests/Examples/Orm/
```

This uses **SQLite in-memory database** - no Docker required, fast execution, complete isolation.

**Configuration:**

Test helper reads database configuration from `.env` file:
- `DB_HOST` - database host (default: `localhost`)
- `DB_PORT` - database port (default: `5433`)
- `DB_NAME` - database name (will append `_test` suffix automatically, e.g., `syntexa` → `syntexa_test`)
- `DB_USER` - database user (default: `test`)
- `DB_PASSWORD` - database password (default: `test`)

**Important:** Test database name automatically gets `_test` suffix to avoid using production database.

**Requirements:**
- Docker and Docker Compose installed
- Port from `.env` (or default 5433) available

**Manual Container Management:**

```bash
# Start PostgreSQL container manually
docker compose -f docker-compose.test.yml up -d

# Stop container
docker compose -f docker-compose.test.yml down

# View logs
docker compose -f docker-compose.test.yml logs -f
```

## Test Structure

All tests extend `OrmExampleTestCase` which provides:

- **Database-agnostic SQL helpers:**
  - `autoIncrementColumn()` - generates `SERIAL PRIMARY KEY` (PostgreSQL) or `INTEGER PRIMARY KEY AUTOINCREMENT` (SQLite)
  - `integerPrimaryKey()` - compatible primary key syntax
  - `textType()`, `integerType()` - database-agnostic type helpers

- **Automatic database selection:**
  - PostgreSQL by default (reads from `.env`, auto-starts Docker container)
  - SQLite if `TEST_WITH_SQLITE=1` is set or PostgreSQL unavailable

- **Schema management:**
  - Each test creates its own schema via `createSchema()`
  - PostgreSQL schema is cleaned between tests automatically

## Writing New Example Tests

1. Extend `OrmExampleTestCase`
2. Implement `createSchema(PDO $pdo): void` to create tables
3. Use helper methods for database-agnostic SQL
4. Write tests that demonstrate ORM features

Example:

```php
class MyFeatureTest extends OrmExampleTestCase
{
    protected function createSchema(PDO $pdo): void
    {
        $autoIncrement = $this->autoIncrementColumn();
        $pdo->exec("CREATE TABLE products (
            id {$autoIncrement},
            name {$this->textType()} NOT NULL
        )");
    }

    public function testMyFeature(): void
    {
        // Your test code here
        // Uses $this->em (EntityManager) and $this->pdo (PDO)
    }
}
```

## Why Two Databases?

- **SQLite**: Fast, no setup, perfect for development and CI
- **PostgreSQL**: Real-world compatibility testing, catches database-specific issues

All SQL is written to be compatible with both databases using helper methods.

