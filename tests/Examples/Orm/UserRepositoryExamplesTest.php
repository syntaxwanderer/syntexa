<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm;

use Syntexa\Tests\Examples\Fixtures\User\Domain as UserDomain;
use Syntexa\Tests\Examples\Fixtures\User\Repository as UserRepository;
use Syntexa\Orm\Migration\Schema\SchemaBuilder;
use Syntexa\Orm\Entity\EntityManager;
use Syntexa\Tests\Examples\Orm\Autowire;

/**
 * Repository-centric examples.
 *
 * This test suite shows how to:
 * - Use a DomainRepository-based repo instead of talking to EntityManager directly
 * - Implement common finder methods (by email, with filters, pagination)
 * - Use repositories from domain services
 */
class UserRepositoryExamplesTest extends OrmExampleTestCase
{
    protected function createSchema(\PDO $pdo): void
    {
        $schema = new SchemaBuilder();
        foreach ($schema->createTable('users')
            ->addColumn('id', 'INTEGER', ['primary' => true])
            ->addColumn('email', 'VARCHAR(255)', ['notNull' => true])
            ->addColumn('name', 'VARCHAR(255)')
            ->addColumn('address_id', 'INTEGER')
            ->addColumn('created_at', 'DATETIME')
            ->addColumn('updated_at', 'DATETIME')
            ->addIndex('email', 'idx_users_email')
            ->build() as $sql) {
            $pdo->exec($sql);
        }
    }

    private function seedUsers(): void
    {
        $now = '2025-01-01 10:00:00';
        $this->insert($this->pdo, "INSERT INTO users (id, email, name, created_at, updated_at) VALUES (1, 'alice@example.com', 'Alice', '$now', '$now')");
        $this->insert($this->pdo, "INSERT INTO users (id, email, name, created_at, updated_at) VALUES (2, 'bob@example.com', 'Bob', '$now', '$now')");
        $this->insert($this->pdo, "INSERT INTO users (id, email, name, created_at, updated_at) VALUES (3, 'charlie@example.org', 'Charlie', '$now', '$now')");
        $this->insert($this->pdo, "INSERT INTO users (id, email, name, created_at, updated_at) VALUES (4, 'dave@example.com', 'Dave', '$now', '$now')");
    }

    /**
     * Basic repository usage: find, findOneBy, findBy, save, remove.
     */
    public function testBasicRepositoryUsage(): void
    {
        $this->seedUsers();

        $container = $this->createContainer([
            UserRepository::class => Autowire::class(UserRepository::class),
        ]);
        /** @var UserRepository $repo */
        $repo = $container->get(UserRepository::class);

        // find() by id returns domain object
        $user = $repo->find(1);
        $this->assertInstanceOf(UserDomain::class, $user);
        $this->assertSame('alice@example.com', $user->getEmail());

        // findOneBy() by email
        $bob = $repo->findOneBy(['email' => 'bob@example.com']);
        $this->assertInstanceOf(UserDomain::class, $bob);
        $this->assertSame('Bob', $bob->getName());

        // findBy() with ordering and limit
        $users = $repo->findBy([], ['email' => 'ASC'], 2, 0);
        $this->assertCount(2, $users);
        $this->assertSame('alice@example.com', $users[0]->getEmail());
        $this->assertSame('bob@example.com', $users[1]->getEmail());

        // save() immediately writes to database
        $newUser = new UserDomain();
        $newUser->setEmail('eve@example.com');
        $newUser->setName('Eve');

        $saved = $repo->save($newUser);
        $this->assertNotNull($saved->getId());

        $loaded = $repo->findOneBy(['email' => 'eve@example.com']);
        $this->assertInstanceOf(UserDomain::class, $loaded);
        $this->assertSame('Eve', $loaded->getName());

        // delete() immediately removes from database
        $repo->delete($loaded);

        $this->assertNull($repo->findOneBy(['email' => 'eve@example.com']));
    }

    /**
     * More domain-style repository methods built on top of DomainRepository.
     */
    public function testCustomDomainMethods(): void
    {
        $this->seedUsers();

        $container = $this->createContainer();

        /** @var EntityManager $em */
        $em = $container->get(EntityManager::class);

        $repo = new class($em) extends UserRepository {
            public function findByEmail(string $email): ?UserDomain
            {
                /** @var UserDomain|null $user */
                $user = $this->findOneBy(['email' => $email]);
                return $user;
            }

            /**
             * Find users whose email ends with given domain.
             *
             * @return UserDomain[]
             */
            public function findByEmailDomain(string $domain): array
            {
                $qb = $this->createQueryBuilder('u');
                $qb->select('u.*')
                    ->where('u.email LIKE :pattern')
                    ->setParameter('pattern', '%' . $domain);

                $rows = $qb->getResult();

                // Map rows back to domain via EntityManager using domain class
                $results = [];
                foreach ($rows as $row) {
                    // Use domain class instead of storage entity
                    $user = $this->em->find(UserDomain::class, (int) $row['id']);
                    if ($user !== null) {
                        $results[] = $user;
                    }
                }

                return $results;
            }
        };

        $alice = $repo->findByEmail('alice@example.com');
        $this->assertInstanceOf(UserDomain::class, $alice);

        $exampleUsers = $repo->findByEmailDomain('example.com');
        $this->assertCount(3, $exampleUsers);
        $emails = array_map(fn (UserDomain $u) => $u->getEmail(), $exampleUsers);
        sort($emails);
        $this->assertSame(
            ['alice@example.com', 'bob@example.com', 'dave@example.com'],
            $emails
        );
    }

    /**
     * Example: using repository inside a domain service.
     */
    public function testRepositoryInDomainService(): void
    {
        $this->seedUsers();

        $container = $this->createContainer([
            UserRepository::class => Autowire::class(UserRepository::class),
            UserRenamingService::class => Autowire::class(UserRenamingService::class),
        ]);

        /** @var UserRenamingService $service */
        $service = $container->get(UserRenamingService::class);

        $updated = $service->renameUserByEmail('alice@example.com', 'Alice Updated');
        $this->assertInstanceOf(UserDomain::class, $updated);
        $this->assertSame('Alice Updated', $updated->getName());

        /** @var UserRepository $reloadedRepo */
        $reloadedRepo = $container->get(UserRepository::class);
        $reloaded = $reloadedRepo->findBy(['email' => 'alice@example.com']);
        $this->assertCount(1, $reloaded);
        $this->assertSame('Alice Updated', $reloaded[0]->getName());
    }
}

/**
 * Domain-style service used in examples to demonstrate DI + repository usage.
 */
class UserRenamingService
{
    public function __construct(
        private UserRepository $repo
    ) {
    }

    public function renameUserByEmail(string $email, string $newName): ?UserDomain
    {
        /** @var UserDomain|null $user */
        $user = $this->repo->findOneBy(['email' => $email]);
        if ($user === null) {
            return null;
        }

        $user->setName($newName);
        return $this->repo->update($user);
    }
}

