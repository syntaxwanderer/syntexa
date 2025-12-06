<?php

declare(strict_types=1);

namespace Syntexa\Orm\Migration;

use PDO;

/**
 * Abstract base class for database migrations
 * 
 * Similar to Doctrine migrations, each migration extends this class
 * and implements up() and down() methods.
 * 
 * @example
 * ```php
 * class Version20241206000001 extends AbstractMigration
 * {
 *     public function up(): void
 *     {
 *         $this->addSql('CREATE TABLE users (...)');
 *     }
 *     
 *     public function down(): void
 *     {
 *         $this->addSql('DROP TABLE users');
 *     }
 * }
 * ```
 */
abstract class AbstractMigration
{
    protected PDO $connection;
    private array $sqlStatements = [];

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Execute migration (apply changes)
     * 
     * Override this method to define migration logic
     */
    abstract public function up(): void;

    /**
     * Rollback migration (undo changes)
     * 
     * Override this method to define rollback logic
     */
    abstract public function down(): void;

    /**
     * Get migration description
     * 
     * Override this method to provide a description
     */
    public function getDescription(): string
    {
        return '';
    }

    /**
     * Add SQL statement to be executed
     * 
     * @param string $sql SQL statement
     */
    protected function addSql(string $sql): void
    {
        $this->sqlStatements[] = trim($sql);
    }

    /**
     * Execute raw SQL
     * 
     * @param string $sql SQL statement
     */
    protected function executeStatement(string $sql): void
    {
        $this->connection->exec($sql);
    }

    /**
     * Get all SQL statements
     * 
     * @return array<string>
     */
    public function getSqlStatements(): array
    {
        return $this->sqlStatements;
    }

    /**
     * Clear SQL statements
     */
    public function clearSqlStatements(): void
    {
        $this->sqlStatements = [];
    }

    /**
     * Check if table exists
     */
    protected function tableExists(string $tableName): bool
    {
        $stmt = $this->connection->prepare("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = :table
            )
        ");
        $stmt->execute(['table' => $tableName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (bool) ($result['exists'] ?? false);
    }

    /**
     * Check if column exists in table
     */
    protected function columnExists(string $tableName, string $columnName): bool
    {
        $stmt = $this->connection->prepare("
            SELECT EXISTS (
                SELECT FROM information_schema.columns 
                WHERE table_schema = 'public' 
                AND table_name = :table
                AND column_name = :column
            )
        ");
        $stmt->execute([
            'table' => $tableName,
            'column' => $columnName,
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (bool) ($result['exists'] ?? false);
    }

    /**
     * Check if index exists
     */
    protected function indexExists(string $indexName): bool
    {
        $stmt = $this->connection->prepare("
            SELECT EXISTS (
                SELECT FROM pg_indexes 
                WHERE schemaname = 'public' 
                AND indexname = :index
            )
        ");
        $stmt->execute(['index' => $indexName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (bool) ($result['exists'] ?? false);
    }
}

