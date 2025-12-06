# Database Migrations

Syntexa ORM uses PHP class-based migrations, similar to Symfony Doctrine.

## Creating a Migration

### Manual Creation

Create a PHP class in `src/infrastructure/migrations/`:

```php
<?php

declare(strict_types=1);

namespace Syntexa\Infrastructure\Migrations;

use Syntexa\Orm\Migration\AbstractMigration;
use Syntexa\Orm\Migration\Schema\SchemaBuilder;

class Version20241206000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table';
    }

    public function up(): void
    {
        $schema = new SchemaBuilder();
        $schema->createTable('users')
            ->addColumn('id', 'SERIAL', ['primary' => true])
            ->addColumn('email', 'VARCHAR(255)', ['notNull' => true, 'unique' => true])
            ->addColumn('password_hash', 'VARCHAR(255)', ['notNull' => true])
            ->addTimestamps()
            ->addIndex('email', 'idx_users_email');

        foreach ($schema->build() as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(): void
    {
        $schema = new SchemaBuilder();
        $this->addSql($schema->dropTable('users'));
    }
}
```

### Naming Convention

- Class name format: `Version{YYYYMMDDHHMMSS}` (e.g., `Version20241206000001`)
- File name: Same as class name (e.g., `Version20241206000001.php`)
- Location: `src/infrastructure/migrations/`
- Namespace: `Syntexa\Infrastructure\Migrations`

## Running Migrations

### Run All Pending Migrations

```bash
bin/syntexa migrate
```

### Run Specific Migration

```bash
bin/syntexa migrate --class="Syntexa\Infrastructure\Migrations\Version20241206000001"
```

### Show Migration Status

```bash
bin/syntexa migrate --status
```

### Rollback Last Migration

```bash
bin/syntexa migrate --rollback
```

### Rollback Specific Migration

```bash
bin/syntexa migrate --rollback=20241206000001
```

## Schema Builder

The `SchemaBuilder` provides a fluent interface for creating tables:

```php
$schema = new SchemaBuilder();
$schema->createTable('users')
    ->addColumn('id', 'SERIAL', ['primary' => true])
    ->addColumn('email', 'VARCHAR(255)', ['notNull' => true, 'unique' => true])
    ->addColumn('name', 'VARCHAR(255)', ['default' => ''])
    ->addTimestamps()
    ->addIndex('email', 'idx_users_email');

foreach ($schema->build() as $sql) {
    $this->addSql($sql);
}
```

### Available Methods

- `createTable(string $tableName)` - Start creating a table
- `addColumn(string $name, string $type, array $options)` - Add a column
- `addTimestamps()` - Add created_at and updated_at columns
- `addIndex(string $column, ?string $indexName)` - Add an index
- `build()` - Get SQL statements
- `dropTable(string $tableName)` - Generate DROP TABLE statement

### Column Options

- `primary` (bool) - Primary key
- `notNull` (bool) - NOT NULL constraint
- `unique` (bool) - UNIQUE constraint
- `default` (mixed) - Default value

## Direct SQL

You can also use direct SQL:

```php
public function up(): void
{
    $this->addSql('CREATE TABLE users (
        id SERIAL PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        name VARCHAR(255) DEFAULT \'\'
    )');
    
    $this->addSql('CREATE INDEX idx_users_email ON users(email)');
}
```

## Helper Methods

The `AbstractMigration` class provides helper methods:

- `tableExists(string $tableName): bool` - Check if table exists
- `columnExists(string $tableName, string $columnName): bool` - Check if column exists
- `indexExists(string $indexName): bool` - Check if index exists
- `executeStatement(string $sql): void` - Execute SQL directly

## Migration Tracking

Migrations are tracked in the `syntexa_migrations` table:

- `version` - Migration version (from class name)
- `executed_at` - Execution timestamp
- `description` - Migration description

## Examples

### Create Table with Foreign Key

```php
public function up(): void
{
    $this->addSql('CREATE TABLE orders (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        total DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');
}
```

### Add Column to Existing Table

```php
public function up(): void
{
    if (!$this->columnExists('users', 'phone')) {
        $this->addSql('ALTER TABLE users ADD COLUMN phone VARCHAR(20)');
    }
}
```

### Drop Table

```php
public function up(): void
{
    $this->addSql('DROP TABLE IF EXISTS old_table');
}
```

## Best Practices

1. **Always implement `down()`** - For rollback support
2. **Use descriptive names** - Class name should reflect what the migration does
3. **Check before creating** - Use helper methods to check if table/column exists
4. **Use transactions** - Migrations run in transactions automatically
5. **Test rollback** - Always test that `down()` works correctly

