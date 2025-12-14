<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm;

use Syntexa\Tests\Examples\Fixtures\User\Storage;

class QueryBuilderJoinsTest extends OrmExampleTestCase
{
    protected function createSchema(\PDO $pdo): void
    {
        $primaryKey = $this->integerPrimaryKey();
        $pdo->exec("CREATE TABLE users (
            id {$primaryKey},
            email TEXT NOT NULL,
            name TEXT NULL,
            address_id INTEGER NULL
        )");

        $pdo->exec("CREATE TABLE addresses (
            id {$primaryKey},
            label TEXT NOT NULL
        )");
    }

    public function testJoinWithAlias(): void
    {
        $this->insert($this->pdo, "INSERT INTO addresses (id, label) VALUES (1, 'HQ')");
        $this->insert($this->pdo, "INSERT INTO users (id, email, name, address_id) VALUES (1, 'a@example.com', 'Alice', 1)");

        $qb = $this->em->createQueryBuilder()
            ->select('u.*, a.label AS address_label')
            ->from(Storage::class, 'u')
            ->leftJoin('addresses', 'a', 'u.address_id = a.id')
            ->where('u.id = :id')
            ->setParameter('id', 1);

        $rows = $qb->getResult();

        $this->assertCount(1, $rows);
        $this->assertSame('HQ', $rows[0]['address_label']);
        $this->assertSame('a@example.com', $rows[0]['email']);
    }
}

