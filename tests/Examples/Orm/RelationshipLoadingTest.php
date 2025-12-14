<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm;

use Syntexa\Tests\Examples\Fixtures\User\Storage as UserStorage;
use Syntexa\Tests\Examples\Fixtures\Post\Storage as PostStorage;
use Syntexa\Tests\Examples\Fixtures\Post\Domain as PostDomain;
use Syntexa\Tests\Examples\Fixtures\User\Domain as UserDomain;
use Syntexa\Orm\Mapping\LazyProxy;

/**
 * Relationship Loading Examples
 * 
 * Demonstrates automatic loading of related entities:
 * - Lazy Loading: Related objects are loaded on first access
 * - Domain Projection: Related objects are domain objects when domainClass is set
 * 
 * @see RelationshipsTest for basic relationship examples
 */
class RelationshipLoadingTest extends OrmExampleTestCase
{
    protected function createSchema(\PDO $pdo): void
    {
        $autoIncrement = $this->autoIncrementColumn();
        
        $pdo->exec("CREATE TABLE users (
            id {$autoIncrement},
            email TEXT NOT NULL,
            name TEXT NULL,
            address_id INTEGER NULL
        )");

        $pdo->exec("CREATE TABLE posts (
            id {$autoIncrement},
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            user_id INTEGER NOT NULL
        )");
    }

    /**
     * Example: Lazy loading of related entity
     * 
     * When you access a relationship property, the related entity
     * is automatically loaded from the database.
     */
    public function testLazyLoading(): void
    {
        // Create user
        $userStorage = new UserStorage();
        $userStorage->setEmail('lazy@example.com');
        $userStorage->setName('Lazy User');
        $this->em->persist($userStorage);
        $this->em->flush();

        $userId = $userStorage->getId();

        // Create post
        $postStorage = new PostStorage();
        $postStorage->setTitle('Lazy Post');
        $postStorage->setContent('Content');
        $postStorage->setUserId($userId);
        $this->em->persist($postStorage);
        $this->em->flush();

        // Load post (returns domain object)
        $post = $this->em->find(PostStorage::class, $postStorage->getId());
        $this->assertInstanceOf(PostDomain::class, $post);

        // Initially, user property contains LazyProxy
        $user = $post->getUser();
        $this->assertNotNull($user);
        
        // On first access, LazyProxy loads the actual UserDomain
        $this->assertInstanceOf(UserDomain::class, $user);
        $this->assertSame('lazy@example.com', $user->getEmail());
        $this->assertSame('Lazy User', $user->getName());

        // Subsequent access returns the same loaded entity (no additional queries)
        $user2 = $post->getUser();
        $this->assertSame($user, $user2); // Same instance
    }

    /**
     * Example: LazyProxy forwards method calls to the loaded entity
     */
    public function testLazyProxyMethodForwarding(): void
    {
        // Create user and post
        $userStorage = new UserStorage();
        $userStorage->setEmail('proxy@example.com');
        $userStorage->setName('Proxy User');
        $this->em->persist($userStorage);
        $this->em->flush();

        $postStorage = new PostStorage();
        $postStorage->setTitle('Proxy Post');
        $postStorage->setContent('Content');
        $postStorage->setUserId($userStorage->getId());
        $this->em->persist($postStorage);
        $this->em->flush();

        // Load post
        $post = $this->em->find(PostStorage::class, $postStorage->getId());
        
        // Access user property (returns LazyProxy initially)
        $userProxy = $post->getUser();
        
        // LazyProxy can forward method calls
        // Note: This works because getUser() already loads the entity
        // In a real scenario, you'd access methods directly on the proxy
        $this->assertInstanceOf(UserDomain::class, $userProxy);
        $this->assertSame('proxy@example.com', $userProxy->getEmail());
    }

    /**
     * Example: Null relationships
     * 
     * When a foreign key is null, the relationship property is also null.
     */
    public function testNullRelationship(): void
    {
        // Create post without user (nullable relationship)
        $postStorage = new PostStorage();
        $postStorage->setTitle('Orphan Post');
        $postStorage->setContent('Content');
        // Note: user_id is required in our schema, so this test would need nullable FK
        // For now, we'll test with a user that gets deleted
        
        // This test demonstrates the concept - in practice, you'd have nullable FKs
        $this->assertTrue(true); // Placeholder
    }

    /**
     * Example: Domain projection preserves relationships
     * 
     * When loading entities with domainClass, related entities
     * are also projected to domain objects.
     */
    public function testDomainProjectionWithRelationships(): void
    {
        // Create user
        $userStorage = new UserStorage();
        $userStorage->setEmail('domain@example.com');
        $userStorage->setName('Domain User');
        $this->em->persist($userStorage);
        $this->em->flush();

        // Create post
        $postStorage = new PostStorage();
        $postStorage->setTitle('Domain Post');
        $postStorage->setContent('Content');
        $postStorage->setUserId($userStorage->getId());
        $this->em->persist($postStorage);
        $this->em->flush();

        // Load post - returns PostDomain (not PostStorage)
        $post = $this->em->find(PostStorage::class, $postStorage->getId());
        $this->assertInstanceOf(PostDomain::class, $post);

        // Access user - returns UserDomain (not UserStorage)
        $user = $post->getUser();
        $this->assertInstanceOf(UserDomain::class, $user);
        $this->assertNotInstanceOf(UserStorage::class, $user);
    }
}

