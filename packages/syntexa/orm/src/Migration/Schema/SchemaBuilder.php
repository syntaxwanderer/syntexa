<?php

declare(strict_types=1);

namespace Syntexa\Orm\Migration\Schema;

/**
 * Schema Builder for creating database tables
 * 
 * Provides a fluent interface for building table schemas
 * 
 * @example
 * ```php
 * $schema = new SchemaBuilder();
 * $schema->createTable('users')
 *     ->addColumn('id', 'SERIAL', ['primary' => true])
 *     ->addColumn('email', 'VARCHAR(255)', ['notNull' => true, 'unique' => true])
 *     ->addColumn('name', 'VARCHAR(255)', ['default' => ''])
 *     ->addTimestamps()
 *     ->addIndex('email', 'idx_users_email');
 * ```
 */
class SchemaBuilder
{
    private array $statements = [];
    private ?string $currentTable = null;
    private array $currentColumns = [];
    private array $currentIndexes = [];

    /**
     * Create a new table
     */
    public function createTable(string $tableName): self
    {
        $this->currentTable = $tableName;
        $this->currentColumns = [];
        $this->currentIndexes = [];
        return $this;
    }

    /**
     * Add column to current table
     * 
     * @param string $name Column name
     * @param string $type Column type (e.g., 'SERIAL', 'VARCHAR(255)', 'INTEGER')
     * @param array<string, mixed> $options Options: primary, notNull, unique, default
     */
    public function addColumn(string $name, string $type, array $options = []): self
    {
        if ($this->currentTable === null) {
            throw new \RuntimeException('No table created. Call createTable() first.');
        }

        $columnDef = $name . ' ' . $type;

        if (!empty($options['primary'])) {
            $columnDef .= ' PRIMARY KEY';
        }

        if (!empty($options['notNull'])) {
            $columnDef .= ' NOT NULL';
        }

        if (!empty($options['unique'])) {
            $columnDef .= ' UNIQUE';
        }

        if (isset($options['default'])) {
            $default = $options['default'];
            if (is_string($default) && $default !== 'CURRENT_TIMESTAMP') {
                $default = "'" . addslashes($default) . "'";
            }
            $columnDef .= ' DEFAULT ' . $default;
        }

        $this->currentColumns[] = $columnDef;
        return $this;
    }

    /**
     * Add timestamps (created_at, updated_at)
     */
    public function addTimestamps(): self
    {
        $this->addColumn('created_at', 'TIMESTAMP', [
            'notNull' => true,
            'default' => 'CURRENT_TIMESTAMP',
        ]);
        $this->addColumn('updated_at', 'TIMESTAMP', [
            'notNull' => true,
            'default' => 'CURRENT_TIMESTAMP',
        ]);
        return $this;
    }

    /**
     * Add index
     * 
     * @param string $column Column name
     * @param string|null $indexName Index name (auto-generated if null)
     */
    public function addIndex(string $column, ?string $indexName = null): self
    {
        if ($this->currentTable === null) {
            throw new \RuntimeException('No table created. Call createTable() first.');
        }

        if ($indexName === null) {
            $indexName = 'idx_' . $this->currentTable . '_' . $column;
        }

        $this->currentIndexes[] = [
            'name' => $indexName,
            'column' => $column,
        ];

        return $this;
    }

    /**
     * Build and get SQL statements
     * 
     * @return array<string> SQL statements
     */
    public function build(): array
    {
        if ($this->currentTable === null) {
            return [];
        }

        $statements = [];

        // CREATE TABLE statement
        $columns = implode(",\n    ", $this->currentColumns);
        $statements[] = "CREATE TABLE IF NOT EXISTS {$this->currentTable} (\n    {$columns}\n)";

        // CREATE INDEX statements
        foreach ($this->currentIndexes as $index) {
            $statements[] = "CREATE INDEX IF NOT EXISTS {$index['name']} ON {$this->currentTable}({$index['column']})";
        }

        return $statements;
    }

    /**
     * Drop table
     */
    public function dropTable(string $tableName): string
    {
        return "DROP TABLE IF EXISTS {$tableName}";
    }
}

