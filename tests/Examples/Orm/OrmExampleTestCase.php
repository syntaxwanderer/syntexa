<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm;

use PDO;
use PHPUnit\Framework\TestCase;
use Syntexa\Orm\Entity\EntityManager;
use Syntexa\Orm\Mapping\DomainContext;
use DI\Container;
use DI\ContainerBuilder;
use function DI\value;
use Syntexa\Tests\Examples\Orm\Autowire;

/**
 * Base test case for ORM examples
 * 
 * Uses PostgreSQL database by default (from .env file, Docker container auto-started).
 * Falls back to SQLite in-memory if PostgreSQL unavailable or TEST_WITH_SQLITE=1 is set.
 * 
 * All SQL is PostgreSQL-compatible using helper methods:
 * - autoIncrementColumn() - generates SERIAL for PostgreSQL, AUTOINCREMENT for SQLite
 * - integerPrimaryKey() - generates compatible primary key syntax
 * 
 * To use SQLite instead:
 *   TEST_WITH_SQLITE=1 ./bin/phpunit tests/Examples/Orm/
 */
abstract class OrmExampleTestCase extends TestCase
{
    protected PDO $pdo;
    protected EntityManager $em;
    private static ?PDO $sharedPostgresConnection = null;

    protected function setUp(): void
    {
        parent::setUp();

        // PostgreSQL is default, fallback to SQLite if disabled or unavailable
        if (PostgresTestHelper::isEnabled()) {
            $pdo = PostgresTestHelper::createConnection();
            if ($pdo !== null) {
                $this->pdo = $pdo;
                // Use shared connection for schema cleanup between tests
                if (self::$sharedPostgresConnection === null) {
                    self::$sharedPostgresConnection = $pdo;
                    $this->cleanupPostgresSchema();
                }
            } else {
                // Fallback to SQLite if PostgreSQL unavailable
                $this->pdo = new PDO('sqlite::memory:');
            }
        } else {
            // Use SQLite if explicitly disabled
            $this->pdo = new PDO('sqlite::memory:');
        }

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->createSchema($this->pdo);

        $this->em = new EntityManager($this->pdo, new DomainContext());
    }

    /**
     * Build a php-di container wired with this test's EntityManager.
     *
     * This lets example tests demonstrate the same DI pattern used in the app,
     * while still using the per-test PDO / EntityManager from OrmExampleTestCase.
     *
     * @param array<string,mixed> $definitions Additional DI definitions (e.g. custom services)
     */
    protected function createContainer(array $definitions = []): Container
    {
        $builder = new ContainerBuilder();
        
        // Enable autowiring so we can use class names directly
        $builder->useAutowiring(true);
        $builder->useAttributes(true);

        $baseDefinitions = [
            EntityManager::class => value($this->em),
        ];

        $builder->addDefinitions(array_merge($baseDefinitions, $definitions));

        return $builder->build();
    }

    /**
     * Get repository instance via DI container
     * 
     * This is the recommended way to get repositories in tests,
     * as it uses the same DI pattern as the application.
     * 
     * @template T
     * @param class-string<T> $repositoryClass Repository class name
     * @return T Repository instance
     */
    protected function getRepository(string $repositoryClass): object
    {
        return Autowire::repository($repositoryClass, $this->em);
    }

    /**
     * Clean up PostgreSQL schema between tests (drop all tables)
     */
    private function cleanupPostgresSchema(): void
    {
        if ($this->getDriverName($this->pdo) !== 'pgsql') {
            return;
        }

        try {
            // Drop all tables
            $this->pdo->exec("
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

    abstract protected function createSchema(PDO $pdo): void;

    protected function insert(PDO $pdo, string $sql, array $params = []): void
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Get database driver name (sqlite, pgsql, mysql, etc.)
     */
    protected function getDriverName(PDO $pdo): string
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Generate auto-increment column definition compatible with both SQLite and PostgreSQL
     */
    protected function autoIncrementColumn(): string
    {
        $driver = $this->getDriverName($this->pdo);
        return match ($driver) {
            'pgsql' => 'SERIAL PRIMARY KEY',
            'sqlite' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT', // fallback to SQLite syntax
        };
    }

    /**
     * Generate auto-increment column definition (without PRIMARY KEY, for composite keys)
     */
    protected function autoIncrement(): string
    {
        $driver = $this->getDriverName($this->pdo);
        return match ($driver) {
            'pgsql' => 'SERIAL',
            'sqlite' => 'INTEGER',
            default => 'INTEGER',
        };
    }

    /**
     * Generate integer primary key (without auto-increment) compatible with both databases
     */
    protected function integerPrimaryKey(): string
    {
        $driver = $this->getDriverName($this->pdo);
        return match ($driver) {
            'pgsql' => 'SERIAL PRIMARY KEY',
            'sqlite' => 'INTEGER PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY',
        };
    }

    /**
     * Get SQL type for text columns (compatible with both databases)
     */
    protected function textType(): string
    {
        $driver = $this->getDriverName($this->pdo);
        return match ($driver) {
            'pgsql' => 'TEXT',
            'sqlite' => 'TEXT',
            default => 'TEXT',
        };
    }

    /**
     * Get SQL type for integer columns
     */
    protected function integerType(): string
    {
        $driver = $this->getDriverName($this->pdo);
        return match ($driver) {
            'pgsql' => 'INTEGER',
            'sqlite' => 'INTEGER',
            default => 'INTEGER',
        };
    }
}

