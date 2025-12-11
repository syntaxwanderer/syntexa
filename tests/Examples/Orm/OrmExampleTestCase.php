<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm;

use PDO;
use PHPUnit\Framework\TestCase;
use Syntexa\Orm\Entity\EntityManager;
use Syntexa\Orm\Mapping\DomainContext;

abstract class OrmExampleTestCase extends TestCase
{
    protected PDO $pdo;
    protected EntityManager $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->createSchema($this->pdo);

        $this->em = new EntityManager($this->pdo, new DomainContext());
    }

    abstract protected function createSchema(PDO $pdo): void;

    protected function insert(PDO $pdo, string $sql, array $params = []): void
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
}

