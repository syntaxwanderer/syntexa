<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm;

use Syntexa\Tests\Examples\Fixtures\User\Domain as UserDomain;
use Syntexa\Tests\Examples\Fixtures\User\Repository as UserRepository;
use Syntexa\Tests\Examples\Fixtures\Address\Domain as AddressDomain;
use Syntexa\Tests\Examples\Fixtures\Address\Repository as AddressRepository;
use Syntexa\Tests\Examples\Fixtures\Post\Domain as PostDomain;
use Syntexa\Tests\Examples\Fixtures\Post\Repository as PostRepository;
use Syntexa\Orm\Migration\Schema\SchemaBuilder;
use Syntexa\Tests\Examples\Orm\Autowire;

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
        // Create address using domain entity and repository
        $addressRepo = $this->getRepository(AddressRepository::class);
        $address = $addressRepo->create();
        $address->setStreet('123 Main St');
        $address->setCity('Kyiv');
        $address->setCountry('Ukraine');
        $savedAddress = $addressRepo->save($address);

        // Create user with address using domain entity and repository
        $userRepo = $this->getRepository(UserRepository::class);
        $user = $userRepo->create();
        $user->setEmail('user@example.com');
        $user->setName('John Doe');
        // Note: address_id is storage-only field, not exposed in domain
        // In real scenario, you would set address relationship via domain method
        $savedUser = $userRepo->save($user);

        // Load user (returns domain object)
        $loadedUser = $userRepo->find($savedUser->getId());

        $this->assertInstanceOf(UserDomain::class, $loadedUser);
        $this->assertSame('user@example.com', $loadedUser->getEmail());

        // Note: address_id is storage-only field, not exposed in domain
        // To access address, you would need to load it separately or use joins
    }

    /**
     * Example: ManyToOne relationship
     * Many Posts belong to one User (via user_id foreign key)
     */
    public function testManyToOneRelationship(): void
    {
        // Create user using domain entity and repository
        $userRepo = $this->getRepository(UserRepository::class);
        $user = $userRepo->create();
        $user->setEmail('author@example.com');
        $user->setName('Author');
        $savedUser = $userRepo->save($user);

        // Create posts for this user using domain entities
        $postRepo = $this->getRepository(PostRepository::class);
        
        $post1 = $postRepo->create();
        $post1->setTitle('First Post');
        $post1->setContent('Content of first post');
        // Business model: set relation via object, not FK
        $post1->setUser($savedUser);
        $postRepo->save($post1);

        $post2 = $postRepo->create();
        $post2->setTitle('Second Post');
        $post2->setContent('Content of second post');
        $post2->setUser($savedUser);
        $postRepo->save($post2);

        // Load posts using repository (returns domain objects)
        $posts = $postRepo->findBy(['userId' => $savedUser->getId()]);

        $this->assertCount(2, $posts);
        $this->assertInstanceOf(PostDomain::class, $posts[0]);
        $this->assertSame('First Post', $posts[0]->getTitle());
        $this->assertNotNull($posts[0]->getUser());
        $this->assertSame($savedUser->getId(), $posts[0]->getUser()->getId());
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
        // Create user using domain entity and repository
        $userRepo = $this->getRepository(UserRepository::class);
        $user = $userRepo->create();
        $user->setEmail('blogger@example.com');
        $user->setName('Blogger');
        $savedUser = $userRepo->save($user);

        $userId = $savedUser->getId();

        // Create multiple posts for this user using domain entities and repository
        $postRepo = $this->getRepository(PostRepository::class);
        for ($i = 1; $i <= 3; $i++) {
            $post = $postRepo->create();
            $post->setTitle("Post {$i}");
            $post->setContent("Content of post {$i}");
            // Business model: set relation via object
            $post->setUser($savedUser);
            $postRepo->save($post);
        }

        // Get all posts for this user using repository (OneToMany: one user has many posts)
        $posts = $postRepo->findBy(['userId' => $userId]);

        $this->assertCount(3, $posts);
        foreach ($posts as $post) {
            $this->assertInstanceOf(PostDomain::class, $post);
            // Relationship is available via object, not raw FK
            $this->assertInstanceOf(UserDomain::class, $post->getUser());
        }
    }

    /**
     * Example: ManyToMany relationship
     * Users and Tags have many-to-many relationship via join table
     */
    public function testManyToManyRelationship(): void
    {
        // Create users using domain entities and repository
        $userRepo = $this->getRepository(UserRepository::class);
        
        $user1 = $userRepo->create();
        $user1->setEmail('user1@example.com');
        $user1->setName('User 1');
        $savedUser1 = $userRepo->save($user1);

        $user2 = $userRepo->create();
        $user2->setEmail('user2@example.com');
        $user2->setName('User 2');
        $savedUser2 = $userRepo->save($user2);

        // Create tags
        $this->insert($this->pdo, "INSERT INTO tags (id, name) VALUES (1, 'php')");
        $this->insert($this->pdo, "INSERT INTO tags (id, name) VALUES (2, 'javascript')");
        $this->insert($this->pdo, "INSERT INTO tags (id, name) VALUES (3, 'python')");

        // Link users to tags via join table
        $this->insert($this->pdo, "INSERT INTO user_tags (user_id, tag_id) VALUES ({$savedUser1->getId()}, 1)");
        $this->insert($this->pdo, "INSERT INTO user_tags (user_id, tag_id) VALUES ({$savedUser1->getId()}, 2)");
        $this->insert($this->pdo, "INSERT INTO user_tags (user_id, tag_id) VALUES ({$savedUser2->getId()}, 2)");
        $this->insert($this->pdo, "INSERT INTO user_tags (user_id, tag_id) VALUES ({$savedUser2->getId()}, 3)");

        // Query: Get all tags for user1 using raw SQL (QueryBuilder expects entity classes)
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.name 
            FROM tags t
            INNER JOIN user_tags ut ON ut.tag_id = t.id
            WHERE ut.user_id = ?
        ");
        $stmt->execute([$savedUser1->getId()]);
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
        // Create user using domain entity and repository
        $userRepo = $this->getRepository(UserRepository::class);
        $user = $userRepo->create();
        $user->setEmail('repo@example.com');
        $user->setName('Repo User');
        $savedUser = $userRepo->save($user);

        $userId = $savedUser->getId();

        // Create posts using domain objects and repository
        $postRepo = $this->getRepository(PostRepository::class);
        
        $post1 = $postRepo->create();
        $post1->setTitle('Repository Post 1');
        $post1->setContent('Content 1');
        $post1->setUser($savedUser);
        $postRepo->save($post1);

        $post2 = $postRepo->create();
        $post2->setTitle('Repository Post 2');
        $post2->setContent('Content 2');
        $post2->setUser($savedUser);
        $postRepo->save($post2);

        // Load user's posts using repository resolved from DI container
        $container = $this->createContainer([
            UserRepository::class => Autowire::class(UserRepository::class),
            PostRepository::class => Autowire::class(PostRepository::class),
        ]);
        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        $loadedUser = $userRepo->find($userId);

        $this->assertInstanceOf(UserDomain::class, $loadedUser);

        // Get posts for this user using repository
        /** @var PostRepository $postRepo */
        $postRepo = $container->get(PostRepository::class);
        $posts = $postRepo->findBy(['userId' => $userId]);

        $this->assertCount(2, $posts);
        $this->assertInstanceOf(PostDomain::class, $posts[0]);
        $this->assertSame('Repository Post 1', $posts[0]->getTitle());
        $this->assertInstanceOf(UserDomain::class, $posts[0]->getUser());
    }
}

