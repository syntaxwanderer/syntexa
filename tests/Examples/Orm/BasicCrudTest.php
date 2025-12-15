<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm;

use Syntexa\Tests\Examples\Fixtures\User\Domain;
use Syntexa\Tests\Examples\Fixtures\User\Repository;
use Syntexa\Tests\Examples\Fixtures\User\Storage;
use Syntexa\Orm\Migration\Schema\SchemaBuilder;

/**
 * Basic CRUD operations example
 * 
 * This test demonstrates the fundamental ORM operations:
 * - Create (INSERT)
 * - Read (SELECT)
 * - Update (UPDATE)
 * - Delete (DELETE)
 * - Collections
 * 
 * @see DomainProjectionTest for domain projection examples
 * @see QueryBuilderJoinsTest for advanced query examples
 */
class BasicCrudTest extends OrmExampleTestCase
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

    /**
     * Example: Create a new entity using Repository (INSERT)
     */
    public function testCreateEntity(): void
    {
        $repo = new Repository($this->em);

        // Create storage entity first (for new entities, we start with storage)
        $userStorage = new Storage();
        $userStorage->setEmail('alice@example.com');
        $userStorage->setName('Alice');

        // Save via EntityManager (returns domain object after save)
        $this->em->persist($userStorage);
        $this->em->flush();

        // ID is auto-generated
        $this->assertNotNull($userStorage->getId());
        $this->assertSame(1, $userStorage->getId());

        // Now we can load it as domain object via repository
        $user = $repo->find($userStorage->getId());
        $this->assertInstanceOf(Domain::class, $user);
        $this->assertSame('alice@example.com', $user->getEmail());
    }

    /**
     * Example: Read entity by ID using Repository (SELECT)
     */
    public function testReadEntityById(): void
    {
        // Setup: create a user first
        $this->insert($this->pdo, "INSERT INTO users (id, email, name) VALUES (1, 'bob@example.com', 'Bob')");

        $repo = new Repository($this->em);

        // Find by ID - returns domain object
        $user = $repo->find(1);

        $this->assertInstanceOf(Domain::class, $user);
        $this->assertSame(1, $user->getId());
        $this->assertSame('bob@example.com', $user->getEmail());
        $this->assertSame('Bob', $user->getName());
    }

    /**
     * Example: Read entity by criteria using Repository (SELECT with WHERE)
     */
    public function testReadEntityByCriteria(): void
    {
        // Setup: create multiple users
        $this->insert($this->pdo, "INSERT INTO users (id, email, name) VALUES (1, 'alice@example.com', 'Alice')");
        $this->insert($this->pdo, "INSERT INTO users (id, email, name) VALUES (2, 'bob@example.com', 'Bob')");
        $this->insert($this->pdo, "INSERT INTO users (id, email, name) VALUES (3, 'charlie@example.com', 'Charlie')");

        $repo = new Repository($this->em);

        // Find one by criteria
        $user = $repo->findOneBy(['email' => 'bob@example.com']);

        $this->assertInstanceOf(Domain::class, $user);
        $this->assertSame('bob@example.com', $user->getEmail());
        $this->assertSame('Bob', $user->getName());
    }

    /**
     * Example: Update entity using Repository (UPDATE)
     */
    public function testUpdateEntity(): void
    {
        // Setup: create a user
        $this->insert($this->pdo, "INSERT INTO users (id, email, name) VALUES (1, 'alice@example.com', 'Alice')");

        $repo = new Repository($this->em);

        // Load entity
        $user = $repo->find(1);
        $this->assertInstanceOf(Domain::class, $user);

        // Modify domain object
        $user->setName('Alice Updated');

        // Save and flush changes via repository
        $repo->save($user);
        $repo->flush();

        // Verify update
        $updated = $repo->find(1);
        $this->assertSame('Alice Updated', $updated->getName());
        $this->assertSame('alice@example.com', $updated->getEmail()); // Unchanged
    }

    /**
     * Example: Delete entity using Repository (DELETE)
     */
    public function testDeleteEntity(): void
    {
        // Setup: create a user
        $this->insert($this->pdo, "INSERT INTO users (id, email, name) VALUES (1, 'alice@example.com', 'Alice')");

        $repo = new Repository($this->em);

        // Load entity
        $user = $repo->find(1);
        $this->assertNotNull($user);

        // Delete via repository
        $repo->remove($user);
        $repo->flush();

        // Verify deletion
        $deleted = $repo->find(1);
        $this->assertNull($deleted);
    }

    /**
     * Example: Get collection of entities using Repository
     */
    public function testGetCollection(): void
    {
        // Setup: create multiple users
        $this->insert($this->pdo, "INSERT INTO users (id, email, name) VALUES (1, 'alice@example.com', 'Alice')");
        $this->insert($this->pdo, "INSERT INTO users (id, email, name) VALUES (2, 'bob@example.com', 'Bob')");
        $this->insert($this->pdo, "INSERT INTO users (id, email, name) VALUES (3, 'charlie@example.com', 'Charlie')");

        $repo = new Repository($this->em);

        // Find all - returns array of domain objects
        $users = $repo->findBy();

        $this->assertCount(3, $users);
        $this->assertInstanceOf(Domain::class, $users[0]);
        $this->assertSame('alice@example.com', $users[0]->getEmail());
        $this->assertSame('bob@example.com', $users[1]->getEmail());
        $this->assertSame('charlie@example.com', $users[2]->getEmail());
    }

    /**
     * Example: Get filtered collection with ordering and pagination using Repository
     */
    public function testGetFilteredCollection(): void
    {
        // Setup: create multiple users
        $this->insert($this->pdo, "INSERT INTO users (id, email, name) VALUES (1, 'alice@example.com', 'Alice')");
        $this->insert($this->pdo, "INSERT INTO users (id, email, name) VALUES (2, 'bob@example.com', 'Bob')");
        $this->insert($this->pdo, "INSERT INTO users (id, email, name) VALUES (3, 'charlie@example.com', 'Charlie')");

        $repo = new Repository($this->em);

        // Find with criteria, ordering, and pagination
        $users = $repo->findBy(
            [], // no criteria = all
            ['email' => 'ASC'], // order by email
            2, // limit
            0  // offset
        );

        $this->assertCount(2, $users);
        $this->assertSame('alice@example.com', $users[0]->getEmail());
        $this->assertSame('bob@example.com', $users[1]->getEmail());
    }

    /**
     * Example: Repository provides clean domain-focused API
     * 
     * All operations work with domain objects, not storage entities.
     * Repository handles mapping automatically.
     */
    public function testRepositoryDomainApi(): void
    {
        // Setup: create a user
        $this->insert($this->pdo, "INSERT INTO users (id, email, name) VALUES (1, 'alice@example.com', 'Alice')");

        $repo = new Repository($this->em);

        // All repository methods return domain objects
        $user = $repo->find(1);
        $this->assertInstanceOf(Domain::class, $user);
        $this->assertSame('alice@example.com', $user->getEmail());

        // Update domain object
        $user->setName('Alice Updated');
        $repo->save($user);
        $repo->flush();

        // Verify update
        $updated = $repo->find(1);
        $this->assertSame('Alice Updated', $updated->getName());
    }
}

