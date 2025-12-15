<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm;

use Syntexa\Tests\Examples\Fixtures\User\Domain as UserDomain;
use Syntexa\Tests\Examples\Fixtures\User\Storage as UserStorage;
use Syntexa\Tests\Examples\Fixtures\User\Repository as UserRepository;
use Syntexa\Tests\Examples\Fixtures\Address\Domain as AddressDomain;
use Syntexa\Tests\Examples\Fixtures\Address\Storage as AddressStorage;
use Syntexa\Tests\Examples\Fixtures\Post\Domain as PostDomain;
use Syntexa\Tests\Examples\Fixtures\Post\Storage as PostStorage;
use Syntexa\Orm\Migration\Schema\SchemaBuilder;

/**
 * Relationships examples
 * 
 * Demonstrates how to work with entity relationships:
 * - OneToOne: User <-> Address (one user has one address)
 * - OneToMany: User -> Posts (one user has many posts)
 * - ManyToOne: Post -> User (many posts belong to one user)
 * - ManyToMany: User <-> Tags (users and tags have many-to-many relationship)
 * 
 * @see BasicCrudTest for basic CRUD operations
 * @see DomainProjectionTest for domain projection examples
 */
class RelationshipsTest extends OrmExampleTestCase
{
    protected function createSchema(\PDO $pdo): void
    {
        // Users table
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

        // Addresses table (OneToOne with User)
        $schema = new SchemaBuilder();
        foreach ($schema->createTable('addresses')
            ->addColumn('id', 'INTEGER', ['primary' => true])
            ->addColumn('street', 'VARCHAR(255)', ['notNull' => true])
            ->addColumn('city', 'VARCHAR(255)', ['notNull' => true])
            ->addColumn('country', 'VARCHAR(255)', ['notNull' => true])
            ->build() as $sql) {
            $pdo->exec($sql);
        }

        // Posts table (ManyToOne to User)
        $schema = new SchemaBuilder();
        foreach ($schema->createTable('posts')
            ->addColumn('id', 'INTEGER', ['primary' => true])
            ->addColumn('title', 'VARCHAR(255)', ['notNull' => true])
            ->addColumn('content', 'TEXT', ['notNull' => true])
            ->addColumn('user_id', 'INTEGER', ['notNull' => true])
            ->build() as $sql) {
            $pdo->exec($sql);
        }

        // Tags table (for ManyToMany)
        $schema = new SchemaBuilder();
        foreach ($schema->createTable('tags')
            ->addColumn('id', 'INTEGER', ['primary' => true])
            ->addColumn('name', 'VARCHAR(255)', ['notNull' => true, 'unique' => true])
            ->build() as $sql) {
            $pdo->exec($sql);
        }

        // Join table for ManyToMany (User <-> Tag)
        $schema = new SchemaBuilder();
        foreach ($schema->createTable('user_tags')
            ->addColumn('user_id', 'INTEGER', ['notNull' => true])
            ->addColumn('tag_id', 'INTEGER', ['notNull' => true])
            ->build() as $sql) {
            $pdo->exec($sql);
        }
    }

    /**
     * Example: OneToOne relationship
     * User has one Address (via address_id foreign key)
     */
    public function testOneToOneRelationship(): void
    {
        // Create address
        $addressStorage = new AddressStorage();
        $addressStorage->setStreet('123 Main St');
        $addressStorage->setCity('Kyiv');
        $addressStorage->setCountry('Ukraine');
        $this->em->persist($addressStorage);
        $this->em->flush();

        // Create user with address
        $userStorage = new UserStorage();
        $userStorage->setEmail('user@example.com');
        $userStorage->setName('John Doe');
        $userStorage->setAddressId($addressStorage->getId());
        $this->em->persist($userStorage);
        $this->em->flush();

        // Load user (returns domain object)
        $userRepo = new UserRepository($this->em);
        $user = $userRepo->find($userStorage->getId());

        $this->assertInstanceOf(UserDomain::class, $user);
        $this->assertSame('user@example.com', $user->getEmail());

        // Note: address_id is storage-only field, not exposed in domain
        // To access address, you would need to load it separately or use joins
    }

    /**
     * Example: ManyToOne relationship
     * Many Posts belong to one User (via user_id foreign key)
     */
    public function testManyToOneRelationship(): void
    {
        // Create user
        $userStorage = new UserStorage();
        $userStorage->setEmail('author@example.com');
        $userStorage->setName('Author');
        $this->em->persist($userStorage);
        $this->em->flush();

        // Create posts for this user
        $post1 = new PostStorage();
        $post1->setTitle('First Post');
        $post1->setContent('Content of first post');
        $post1->setUserId($userStorage->getId());
        $this->em->persist($post1);

        $post2 = new PostStorage();
        $post2->setTitle('Second Post');
        $post2->setContent('Content of second post');
        $post2->setUserId($userStorage->getId());
        $this->em->persist($post2);

        $this->em->flush();

        // Load posts (returns domain objects)
        $posts = $this->em->findBy(PostStorage::class, ['userId' => $userStorage->getId()]);

        $this->assertCount(2, $posts);
        $this->assertInstanceOf(PostDomain::class, $posts[0]);
        $this->assertSame('First Post', $posts[0]->getTitle());
        $this->assertSame($userStorage->getId(), $posts[0]->getUserId());
        $this->assertSame('Second Post', $posts[1]->getTitle());

        // Test lazy loading: accessing user property should load UserDomain
        $post = $posts[0];
        $user = $post->getUser();
        
        $this->assertNotNull($user);
        $this->assertInstanceOf(\Syntexa\Tests\Examples\Fixtures\User\Domain::class, $user);
        $this->assertSame('author@example.com', $user->getEmail());
        $this->assertSame('Author', $user->getName());
    }

    /**
     * Example: OneToMany relationship (inverse of ManyToOne)
     * One User has many Posts
     * 
     * Note: This is the inverse side of ManyToOne.
     * In practice, you query posts by user_id to get user's posts.
     */
    public function testOneToManyRelationship(): void
    {
        // Create user
        $userStorage = new UserStorage();
        $userStorage->setEmail('blogger@example.com');
        $userStorage->setName('Blogger');
        $this->em->persist($userStorage);
        $this->em->flush();

        $userId = $userStorage->getId();

        // Create multiple posts for this user
        for ($i = 1; $i <= 3; $i++) {
            $post = new PostStorage();
            $post->setTitle("Post {$i}");
            $post->setContent("Content of post {$i}");
            $post->setUserId($userId);
            $this->em->persist($post);
        }
        $this->em->flush();

        // Get all posts for this user (OneToMany: one user has many posts)
        $posts = $this->em->findBy(PostStorage::class, ['userId' => $userId]);

        $this->assertCount(3, $posts);
        foreach ($posts as $post) {
            $this->assertInstanceOf(PostDomain::class, $post);
            $this->assertSame($userId, $post->getUserId());
        }
    }

    /**
     * Example: ManyToMany relationship
     * Users and Tags have many-to-many relationship via join table
     */
    public function testManyToManyRelationship(): void
    {
        // Create users
        $user1 = new UserStorage();
        $user1->setEmail('user1@example.com');
        $user1->setName('User 1');
        $this->em->persist($user1);

        $user2 = new UserStorage();
        $user2->setEmail('user2@example.com');
        $user2->setName('User 2');
        $this->em->persist($user2);

        $this->em->flush();

        // Create tags
        $this->insert($this->pdo, "INSERT INTO tags (id, name) VALUES (1, 'php')");
        $this->insert($this->pdo, "INSERT INTO tags (id, name) VALUES (2, 'javascript')");
        $this->insert($this->pdo, "INSERT INTO tags (id, name) VALUES (3, 'python')");

        // Link users to tags via join table
        $this->insert($this->pdo, "INSERT INTO user_tags (user_id, tag_id) VALUES ({$user1->getId()}, 1)");
        $this->insert($this->pdo, "INSERT INTO user_tags (user_id, tag_id) VALUES ({$user1->getId()}, 2)");
        $this->insert($this->pdo, "INSERT INTO user_tags (user_id, tag_id) VALUES ({$user2->getId()}, 2)");
        $this->insert($this->pdo, "INSERT INTO user_tags (user_id, tag_id) VALUES ({$user2->getId()}, 3)");

        // Query: Get all tags for user1 using raw SQL (QueryBuilder expects entity classes)
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.name 
            FROM tags t
            INNER JOIN user_tags ut ON ut.tag_id = t.id
            WHERE ut.user_id = ?
        ");
        $stmt->execute([$user1->getId()]);
        $tags = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(2, $tags);
        $tagNames = array_column($tags, 'name');
        $this->assertContains('php', $tagNames);
        $this->assertContains('javascript', $tagNames);

        // Query: Get all users for tag 'javascript' using raw SQL
        $stmt2 = $this->pdo->prepare("
            SELECT u.id, u.email, u.name
            FROM users u
            INNER JOIN user_tags ut ON ut.user_id = u.id
            INNER JOIN tags t ON ut.tag_id = t.id
            WHERE t.name = ?
        ");
        $stmt2->execute(['javascript']);

        $users = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(2, $users);
        $userEmails = array_column($users, 'email');
        $this->assertContains('user1@example.com', $userEmails);
        $this->assertContains('user2@example.com', $userEmails);
    }

    /**
     * Example: Working with relationships using Repository
     * Repository provides clean API for related entities
     */
    public function testRelationshipsWithRepository(): void
    {
        // Create user
        $userStorage = new UserStorage();
        $userStorage->setEmail('repo@example.com');
        $userStorage->setName('Repo User');
        $this->em->persist($userStorage);
        $this->em->flush();

        $userId = $userStorage->getId();

        // Create posts using domain objects
        $post1 = new PostDomain();
        $post1->setTitle('Repository Post 1');
        $post1->setContent('Content 1');
        $post1->setUserId($userId);

        $post2 = new PostDomain();
        $post2->setTitle('Repository Post 2');
        $post2->setContent('Content 2');
        $post2->setUserId($userId);

        // Save posts via EntityManager (they will be mapped to storage)
        $this->em->persist($post1);
        $this->em->persist($post2);
        $this->em->flush();

        // Load user's posts using repository
        $userRepo = new UserRepository($this->em);
        $user = $userRepo->find($userId);

        $this->assertInstanceOf(UserDomain::class, $user);

        // Get posts for this user
        $posts = $this->em->findBy(PostStorage::class, ['userId' => $userId]);

        $this->assertCount(2, $posts);
        $this->assertInstanceOf(PostDomain::class, $posts[0]);
        $this->assertSame('Repository Post 1', $posts[0]->getTitle());
        $this->assertSame($userId, $posts[0]->getUserId());
    }
}

