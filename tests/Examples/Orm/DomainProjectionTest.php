<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm;

use Syntexa\Tests\Examples\Fixtures\User\Domain;
use Syntexa\Tests\Examples\Fixtures\User\Repository;
use Syntexa\Tests\Examples\Fixtures\User\Storage;
use Syntexa\Orm\Migration\Schema\SchemaBuilder;

class DomainProjectionTest extends OrmExampleTestCase
{
    protected function createSchema(\PDO $pdo): void
    {
        $schema = new SchemaBuilder();
        foreach ($schema->createTable('users')
            ->addColumn('id', 'INTEGER', ['primary' => true])
            ->addColumn('email', 'VARCHAR(255)', ['notNull' => true])
            ->addColumn('name', 'VARCHAR(255)')
            ->addColumn('address_id', 'INTEGER')
            ->addIndex('email', 'idx_users_email')
            ->build() as $sql) {
            $pdo->exec($sql);
        }
    }

    public function testSelectiveHydrationSkipsStorageOnlyFields(): void
    {
        $this->insert($this->pdo, "INSERT INTO users (id, email, name, address_id) VALUES (1, 'a@example.com', 'Alice', 10)");

        // Use repository with domain class instead of storage entity
        $repo = $this->getRepository(Repository::class);
        $user = $repo->find(1);

        $this->assertInstanceOf(Domain::class, $user);
        $this->assertSame(1, $user->getId());
        $this->assertSame('a@example.com', $user->getEmail());
        $this->assertSame('Alice', $user->getName());
        // address_id is not exposed in the domain model, so it is skipped
        $this->assertFalse(property_exists($user, 'addressId'));
    }

    public function testReverseMappingUpdatesStorageWithoutTouchingSkippedFields(): void
    {
        $this->insert($this->pdo, "INSERT INTO users (id, email, name, address_id) VALUES (1, 'a@example.com', 'Alice', 10)");

        // Use repository with domain class instead of storage entity
        $repo = $this->getRepository(Repository::class);
        /** @var Domain $user */
        $user = $repo->find(1);
        $user->setName('Alice Updated');

        // Update domain via repository; EntityManager will map back to storage and issue UPDATE
        $repo->update($user);

        $row = $this->pdo->query('SELECT name, address_id FROM users WHERE id = 1')->fetch();
        $this->assertSame('Alice Updated', $row['name']);
        // address_id was not exposed in domain, so remains unchanged
        $this->assertSame(10, (int) $row['address_id']);
    }

    public function testRepositoryCrudUsesDomainProjection(): void
    {
        $this->insert($this->pdo, "INSERT INTO users (id, email, name, address_id) VALUES (2, 'b@example.com', 'Bob', NULL)");

        $repo = $this->getRepository(Repository::class);
        /** @var Domain $user */
        $user = $repo->find(2);

        $this->assertInstanceOf(Domain::class, $user);
        $this->assertSame('Bob', $user->getName());

        $user->setName('Bobby');
        $repo->update($user);

        $row = $this->pdo->query('SELECT name FROM users WHERE id = 2')->fetch();
        $this->assertSame('Bobby', $row['name']);
    }

    /**
     * Example: Create and persist starting from Domain object (no Storage in userland)
     */
    public function testCreateFromDomain(): void
    {
        $repo = $this->getRepository(Repository::class);
        
        // Create domain object via repository (recommended way)
        $user = $repo->create();
        $user->setEmail('domain-create@example.com');
        $user->setName('Domain Created');

        // Save domain via repository; EntityManager resolves storage and inserts row
        $repo->save($user);

        // Reload via repository as Domain
        $repo = $this->getRepository(Repository::class);
        $loaded = $repo->findOneBy(['email' => 'domain-create@example.com']);
        $this->assertInstanceOf(Domain::class, $loaded);
        $this->assertSame('Domain Created', $loaded->getName());
        $this->assertNotNull($loaded->getId());
    }
}

