<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm;

use Syntexa\Tests\Examples\Fixtures\User\Domain;
use Syntexa\Tests\Examples\Fixtures\User\Repository;
use Syntexa\Tests\Examples\Fixtures\User\Storage;

class DomainProjectionTest extends OrmExampleTestCase
{
    protected function createSchema(\PDO $pdo): void
    {
        $primaryKey = $this->integerPrimaryKey();
        $sql = "CREATE TABLE users (
            id {$primaryKey},
            email TEXT NOT NULL,
            name TEXT NULL,
            address_id INTEGER NULL
        )";
        $pdo->exec($sql);
    }

    public function testSelectiveHydrationSkipsStorageOnlyFields(): void
    {
        $this->insert($this->pdo, "INSERT INTO users (id, email, name, address_id) VALUES (1, 'a@example.com', 'Alice', 10)");

        $user = $this->em->find(Storage::class, 1);

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

        /** @var Domain $user */
        $user = $this->em->find(Storage::class, 1);
        $user->setName('Alice Updated');

        // Persist domain; EntityManager will map back to storage and issue UPDATE
        $this->em->persist($user);
        $this->em->flush();

        $row = $this->pdo->query('SELECT name, address_id FROM users WHERE id = 1')->fetch();
        $this->assertSame('Alice Updated', $row['name']);
        // address_id was not exposed in domain, so remains unchanged
        $this->assertSame(10, (int) $row['address_id']);
    }

    public function testRepositoryCrudUsesDomainProjection(): void
    {
        $this->insert($this->pdo, "INSERT INTO users (id, email, name, address_id) VALUES (2, 'b@example.com', 'Bob', NULL)");

        $repo = new Repository($this->em);
        /** @var Domain $user */
        $user = $repo->find(2);

        $this->assertInstanceOf(Domain::class, $user);
        $this->assertSame('Bob', $user->getName());

        $user->setName('Bobby');
        $repo->save($user);
        $repo->flush();

        $row = $this->pdo->query('SELECT name FROM users WHERE id = 2')->fetch();
        $this->assertSame('Bobby', $row['name']);
    }
}

