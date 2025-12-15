<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm;

use Syntexa\Infrastructure\Database\User as StorageUser;
use Syntexa\Modules\UserFrontend\Domain\User as DomainUser;
use function DI\autowire;
use Syntexa\Orm\Migration\Schema\SchemaBuilder;

/**
 * Demonstrates domain extension via domain traits (no ORM attributes in domain)
 * and storage extension via entity-part traits.
 */
class DomainExtensionTest extends OrmExampleTestCase
{
    protected function createSchema(\PDO $pdo): void
    {
        $schema = new SchemaBuilder();
        foreach ($schema->createTable('users')
            ->addColumn('id', 'INTEGER', ['primary' => true])
            ->addColumn('email', 'VARCHAR(255)', ['notNull' => true])
            ->addColumn('password_hash', 'VARCHAR(255)', ['notNull' => true])
            ->addColumn('name', 'VARCHAR(255)')
            ->addColumn('created_at', 'DATETIME', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'DATETIME', ['default' => 'CURRENT_TIMESTAMP'])
            // Marketing extension columns (from infrastructure trait)
            ->addColumn('birthday', 'DATETIME')
            ->addColumn('last_store_visit_at', 'DATETIME')
            ->addColumn('marketing_opt_in', 'BOOLEAN', ['default' => 0])
            ->addColumn('favorite_category', 'VARCHAR(64)')
            ->addIndex('email', 'idx_users_email')
            ->build() as $sql) {
            $pdo->exec($sql);
        }
    }

    public function testDomainExtensionHydration(): void
    {
        $this->insert($this->pdo, "
            INSERT INTO users (id, email, password_hash, name, birthday, marketing_opt_in, favorite_category)
            VALUES (1, 'ext@example.com', 'hash', 'Extended', '2000-01-01 00:00:00', 1, 'tech')
        ");

        /** @var DomainUser $user */
        $user = $this->em->find(StorageUser::class, 1);

        $this->assertInstanceOf(DomainUser::class, $user);
        $this->assertSame('ext@example.com', $user->getEmail());
        $this->assertSame('Extended', $user->getName());
        $this->assertTrue($user->hasMarketingOptIn());
        $this->assertSame('tech', $user->getFavoriteCategory());
        $this->assertNotNull($user->getBirthday());
        $this->assertSame('2000-01-01', $user->getBirthday()?->format('Y-m-d'));
    }

    public function testDomainExtensionPersist(): void
    {
        $container = $this->createContainer();

        /** @var \Syntexa\Orm\Entity\EntityManager $em */
        $em = $container->get(\Syntexa\Orm\Entity\EntityManager::class);

        // Prime metadata so EntityManager knows mapping between DomainUser and StorageUser
        $em->findBy(StorageUser::class, []);

        $user = new DomainUser();
        $user->setEmail('save@example.com');
        $user->setPassword('secret');
        $user->setName('Saver');
        $user->setMarketingOptIn(true);
        $user->setFavoriteCategory('books');

        $em->persist($user);
        $em->flush();

        $row = $this->pdo->query("SELECT email, marketing_opt_in, favorite_category FROM users WHERE email = 'save@example.com'")->fetch();
        $this->assertSame('save@example.com', $row['email']);
        $this->assertSame('1', (string) $row['marketing_opt_in']);
        $this->assertSame('books', $row['favorite_category']);
    }
}

