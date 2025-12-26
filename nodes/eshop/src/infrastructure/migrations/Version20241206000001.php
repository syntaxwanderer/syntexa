<?php

declare(strict_types=1);

namespace Syntexa\Infrastructure\Migrations;

use Syntexa\Orm\Migration\AbstractMigration;
use Syntexa\Orm\Migration\Schema\SchemaBuilder;

/**
 * Create users table
 * 
 * This migration creates the users table with:
 * - id (SERIAL PRIMARY KEY)
 * - email (VARCHAR(255) UNIQUE NOT NULL)
 * - password_hash (VARCHAR(255) NOT NULL)
 * - name (VARCHAR(255) nullable)
 * - created_at, updated_at (timestamps)
 * - index on email
 */
class Version20241206000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table';
    }

    public function up(): void
    {
        // Using SchemaBuilder for cleaner syntax
        $schema = new SchemaBuilder();
        $schema->createTable('users')
            ->addColumn('id', 'SERIAL', ['primary' => true])
            ->addColumn('email', 'VARCHAR(255)', ['notNull' => true, 'unique' => true])
            ->addColumn('password_hash', 'VARCHAR(255)', ['notNull' => true])
            ->addColumn('name', 'VARCHAR(255)', ['default' => ''])
            ->addTimestamps()
            ->addIndex('email', 'idx_users_email');

        foreach ($schema->build() as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(): void
    {
        // Drop table and index
        $schema = new SchemaBuilder();
        $this->addSql($schema->dropTable('users'));
        $this->addSql('DROP INDEX IF EXISTS idx_users_email');
    }
}

