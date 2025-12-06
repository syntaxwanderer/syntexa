<?php

declare(strict_types=1);

namespace Syntexa\Orm\Migration;

use PDO;
use ReflectionClass;

/**
 * Executes database migrations
 * 
 * Similar to Doctrine migrations, this class:
 * - Tracks executed migrations in a migrations table
 * - Executes migrations in order
 * - Supports rollback
 */
class MigrationExecutor
{
    private PDO $connection;
    private const MIGRATIONS_TABLE = 'syntexa_migrations';

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->ensureMigrationsTable();
    }

    /**
     * Ensure migrations table exists
     */
    private function ensureMigrationsTable(): void
    {
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS " . self::MIGRATIONS_TABLE . " (
                version VARCHAR(255) PRIMARY KEY,
                executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                description TEXT
            )
        ");
    }

    /**
     * Get all executed migration versions
     * 
     * @return array<string>
     */
    public function getExecutedMigrations(): array
    {
        $stmt = $this->connection->query("
            SELECT version FROM " . self::MIGRATIONS_TABLE . " 
            ORDER BY executed_at ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Check if migration is executed
     */
    public function isExecuted(string $version): bool
    {
        $stmt = $this->connection->prepare("
            SELECT COUNT(*) FROM " . self::MIGRATIONS_TABLE . " 
            WHERE version = :version
        ");
        $stmt->execute(['version' => $version]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Mark migration as executed
     */
    public function markExecuted(string $version, string $description = ''): void
    {
        $stmt = $this->connection->prepare("
            INSERT INTO " . self::MIGRATIONS_TABLE . " (version, description)
            VALUES (:version, :description)
            ON CONFLICT (version) DO NOTHING
        ");
        $stmt->execute([
            'version' => $version,
            'description' => $description,
        ]);
    }

    /**
     * Mark migration as not executed (rollback)
     */
    public function markNotExecuted(string $version): void
    {
        $stmt = $this->connection->prepare("
            DELETE FROM " . self::MIGRATIONS_TABLE . " 
            WHERE version = :version
        ");
        $stmt->execute(['version' => $version]);
    }

    /**
     * Execute migration
     * 
     * @param AbstractMigration $migration Migration instance
     * @param string $version Migration version (class name or timestamp)
     */
    public function execute(AbstractMigration $migration, string $version): void
    {
        if ($this->isExecuted($version)) {
            return; // Already executed
        }

        $this->connection->beginTransaction();
        try {
            // Clear previous SQL statements
            $migration->clearSqlStatements();
            
            // Call up() method
            $migration->up();
            
            // Execute all SQL statements
            $sqlStatements = $migration->getSqlStatements();
            foreach ($sqlStatements as $sql) {
                if (!empty($sql)) {
                    $this->connection->exec($sql);
                }
            }
            
            // Mark as executed
            $description = $migration->getDescription();
            $this->markExecuted($version, $description);
            
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw new \RuntimeException(
                "Migration {$version} failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Rollback migration
     * 
     * @param AbstractMigration $migration Migration instance
     * @param string $version Migration version
     */
    public function rollback(AbstractMigration $migration, string $version): void
    {
        if (!$this->isExecuted($version)) {
            return; // Not executed
        }

        $this->connection->beginTransaction();
        try {
            // Clear previous SQL statements
            $migration->clearSqlStatements();
            
            // Call down() method
            $migration->down();
            
            // Execute all SQL statements
            $sqlStatements = $migration->getSqlStatements();
            foreach ($sqlStatements as $sql) {
                if (!empty($sql)) {
                    $this->connection->exec($sql);
                }
            }
            
            // Mark as not executed
            $this->markNotExecuted($version);
            
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw new \RuntimeException(
                "Rollback of migration {$version} failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get migration version from class name
     * 
     * Extracts version from class name like Version20241206000001
     * 
     * @param string $className Full class name
     * @return string Version string
     */
    public static function getVersionFromClassName(string $className): string
    {
        $reflection = new ReflectionClass($className);
        $shortName = $reflection->getShortName();
        
        // Extract version from class name (e.g., Version20241206000001 -> 20241206000001)
        if (preg_match('/Version(\d+)/', $shortName, $matches)) {
            return $matches[1];
        }
        
        // Fallback to class name
        return $shortName;
    }
}

