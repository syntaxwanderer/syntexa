<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm;

use Syntexa\Tests\Examples\Fixtures\User\Domain;
use Syntexa\Orm\Migration\Schema\SchemaBuilder;

class QueryBuilderJoinsTest extends OrmExampleTestCase
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

        $schema = new SchemaBuilder();
        foreach ($schema->createTable('addresses')
            ->addColumn('id', 'INTEGER', ['primary' => true])
            ->addColumn('label', 'VARCHAR(255)', ['notNull' => true])
            ->build() as $sql) {
            $pdo->exec($sql);
        }
    }

    public function testJoinWithAlias(): void
    {
        $this->insert($this->pdo, "INSERT INTO addresses (id, label) VALUES (1, 'HQ')");
        $this->insert($this->pdo, "INSERT INTO users (id, email, name, address_id) VALUES (1, 'a@example.com', 'Alice', 1)");

        // QueryBuilder works with storage class internally, but we use domain class for API
        // The repository's createQueryBuilder uses storage class under the hood
        $userRepo = $this->getRepository(\Syntexa\Tests\Examples\Fixtures\User\Repository::class);
        $qb = $userRepo->createQueryBuilder('u')
            ->select('u.*, a.label AS address_label')
            ->leftJoin('addresses', 'a', 'u.address_id = a.id')
            ->where('u.id = :id')
            ->setParameter('id', 1);

        $rows = $qb->getResult();

        $this->assertCount(1, $rows);
        $this->assertSame('HQ', $rows[0]['address_label']);
        $this->assertSame('a@example.com', $rows[0]['email']);
    }
}

