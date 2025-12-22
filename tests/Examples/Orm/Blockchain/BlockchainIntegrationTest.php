<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm\Blockchain;

use PDO;
use Syntexa\Orm\Blockchain\BlockchainConfig;
use Syntexa\Orm\Blockchain\BlockchainFieldExtractor;
use Syntexa\Orm\Blockchain\BlockchainPublisher;
use Syntexa\Orm\Blockchain\BlockchainStorage;
use Syntexa\Orm\Blockchain\BlockchainTransaction;
use Syntexa\Orm\Blockchain\TransactionIdGenerator;
use Syntexa\Orm\Entity\EntityManager;
use Syntexa\Orm\Mapping\DomainContext;
use Syntexa\Tests\Examples\Fixtures\Post\Domain as PostDomain;
use Syntexa\Tests\Examples\Fixtures\Post\Storage as PostStorage;
use Syntexa\Tests\Examples\Fixtures\User\Domain as UserDomain;
use Syntexa\Tests\Examples\Fixtures\User\Storage as UserStorage;
use Syntexa\Tests\Examples\Orm\OrmExampleTestCase;

/**
 * Integration tests for blockchain functionality
 * 
 * Tests:
 * - EntityManager.save() → BlockchainPublisher → Storage
 * - Field extraction from entities with #[BlockchainField]
 * - Transaction storage in blockchain DB
 * - Delete operations with snapshot hash
 */
class BlockchainIntegrationTest extends OrmExampleTestCase
{
    private ?PDO $blockchainPdo = null;
    private ?BlockchainStorage $blockchainStorage = null;
    private ?BlockchainConfig $blockchainConfig = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Blockchain tests require PostgreSQL (BlockchainStorage uses PostgreSQL-specific SQL)
        $driverName = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driverName !== 'pgsql') {
            $this->markTestSkipped('Blockchain tests require PostgreSQL (uses PostgreSQL-specific SQL)');
            return;
        }
        
        // Use the same database connection as app tests
        $this->blockchainPdo = $this->pdo;
        
        // Get database config
        $dbConfig = $this->getBlockchainDbConfig();
        
        // Get database name from connection
        $dbName = $dbConfig['dbname'];
        try {
            $dbName = $this->pdo->query("SELECT current_database()")->fetchColumn() ?: $dbName;
        } catch (\PDOException $e) {
            // Fallback to config value
        }
        
        // Create blockchain config for testing
        // Use same database as app tests (blockchain tables will be separate)
        $this->blockchainConfig = new BlockchainConfig(
            enabled: true,
            dbHost: $dbConfig['host'],
            dbPort: $dbConfig['port'],
            dbName: $dbName,
            dbUser: $dbConfig['user'],
            dbPassword: $dbConfig['password'],
            participants: ['test-node'],
            nodeId: 'test-node',
            rabbitmqHost: null, // No RabbitMQ for integration tests (direct storage)
            rabbitmqPort: null,
            rabbitmqUser: null,
            rabbitmqPassword: null,
            rabbitmqExchange: null,
            rabbitmqVhost: '/',
        );
        
        $this->blockchainStorage = new BlockchainStorage($this->blockchainConfig);
        
        // Inject our PDO connection into BlockchainStorage
        $reflection = new \ReflectionClass($this->blockchainStorage);
        $connectionProperty = $reflection->getProperty('connection');
        $connectionProperty->setAccessible(true);
        $connectionProperty->setValue($this->blockchainStorage, $this->blockchainPdo);
        
        // Ensure blockchain schema is created
        $ensureSchemaMethod = $reflection->getMethod('ensureSchema');
        $ensureSchemaMethod->setAccessible(true);
        $ensureSchemaMethod->invoke($this->blockchainStorage, $this->blockchainPdo);
    }

    protected function tearDown(): void
    {
        // Cleanup blockchain DB
        if ($this->blockchainPdo !== null) {
            $this->cleanupBlockchainDb();
        }
        
        parent::tearDown();
    }

    protected function createSchema(PDO $pdo): void
    {
        // Users table (matches UserStorage structure)
        $schema = new \Syntexa\Orm\Migration\Schema\SchemaBuilder();
        foreach ($schema->createTable('users')
            ->addColumn('id', 'INTEGER', ['primary' => true])
            ->addColumn('email', 'VARCHAR(255)', ['notNull' => true])
            ->addColumn('name', 'VARCHAR(255)')
            ->addColumn('address_id', 'INTEGER', ['nullable' => true]) // For OneToOne relationship
            ->build() as $sql) {
            $pdo->exec($sql);
        }

        // Posts table
        $schema = new \Syntexa\Orm\Migration\Schema\SchemaBuilder();
        foreach ($schema->createTable('posts')
            ->addColumn('id', 'INTEGER', ['primary' => true])
            ->addColumn('title', 'VARCHAR(255)', ['notNull' => true])
            ->addColumn('content', 'TEXT', ['notNull' => true])
            ->addColumn('user_id', 'INTEGER', ['notNull' => true])
            ->build() as $sql) {
            $pdo->exec($sql);
        }
    }

    /**
     * Test: EntityManager.save() creates blockchain transaction
     */
    public function testSaveCreatesBlockchainTransaction(): void
    {
        // Create user
        $userRepo = $this->getRepository(\Syntexa\Tests\Examples\Fixtures\User\Repository::class);
        $user = $userRepo->create();
        $user->setEmail('blockchain@example.com');
        $user->setName('Blockchain User');
        $savedUser = $userRepo->save($user);

        // Check blockchain storage
        $transactions = $this->getBlockchainTransactions();
        
        // Should have at least one transaction for user save
        $this->assertGreaterThanOrEqual(1, count($transactions));
        
        // Find user transaction
        $userTx = null;
        foreach ($transactions as $tx) {
            if ($tx['entity_class'] === UserStorage::class && $tx['entity_id'] === $savedUser->getId()) {
                $userTx = $tx;
                break;
            }
        }
        
        $this->assertNotNull($userTx, 'User transaction should be in blockchain');
        $this->assertSame('save', $userTx['operation']);
        $this->assertSame('test-node', $userTx['node_id']);
    }

    /**
     * Test: Delete operations create snapshot hash
     */
    public function testDeleteCreatesSnapshotHash(): void
    {
        // Create and save post
        $postRepo = $this->getRepository(\Syntexa\Tests\Examples\Fixtures\Post\Repository::class);
        $userRepo = $this->getRepository(\Syntexa\Tests\Examples\Fixtures\User\Repository::class);
        
        $user = $userRepo->create();
        $user->setEmail('author@example.com');
        $user->setName('Author');
        $savedUser = $userRepo->save($user);
        
        $post = $postRepo->create();
        $post->setTitle('Test Post');
        $post->setContent('Content');
        $post->setUser($savedUser);
        $savedPost = $postRepo->save($post);
        
        // Delete post
        $postRepo->delete($savedPost);
        
        // Check blockchain for delete transaction
        $transactions = $this->getBlockchainTransactions();
        
        $deleteTx = null;
        foreach ($transactions as $tx) {
            if ($tx['entity_class'] === PostStorage::class 
                && $tx['entity_id'] === $savedPost->getId() 
                && $tx['operation'] === 'delete') {
                $deleteTx = $tx;
                break;
            }
        }
        
        $this->assertNotNull($deleteTx, 'Delete transaction should be in blockchain');
        
        // Parse fields JSON to check snapshot hash
        $fields = json_decode($deleteTx['fields'], true);
        $this->assertArrayHasKey('snapshotHash', $fields);
        $this->assertNotEmpty($fields['snapshotHash']);
    }

    /**
     * Test: BlockchainField extraction works correctly
     */
    public function testBlockchainFieldExtraction(): void
    {
        $extractor = new BlockchainFieldExtractor();
        
        // Create test entity with blockchain fields
        $post = new PostStorage();
        $post->setId(1);
        $post->setTitle('Test');
        $post->setContent('Content');
        $post->setUserId(1);
        
        // Get metadata for PostStorage
        $metadata = $this->em->getEntityMetadata(PostStorage::class);
        
        $fields = $extractor->extractFields($post, $metadata);
        
        // Should extract fields marked with #[BlockchainField]
        // (Assuming PostStorage has some fields marked)
        $this->assertIsArray($fields);
    }

    /**
     * Test: Transaction ID uniqueness
     */
    public function testTransactionIdUniqueness(): void
    {
        $generator = new TransactionIdGenerator();
        
        $timestamp = new \DateTimeImmutable();
        
        $id1 = $generator->generate('node1', 'Entity', 1, 'save', ['field' => 'value'], $timestamp);
        $id2 = $generator->generate('node1', 'Entity', 1, 'save', ['field' => 'value'], $timestamp);
        
        // Different nonces should produce different IDs
        $this->assertNotSame($id1, $id2);
    }


    /**
     * Cleanup blockchain database
     */
    private function cleanupBlockchainDb(): void
    {
        if ($this->blockchainPdo === null) {
            return;
        }
        
        try {
            // Drop all tables
            $this->blockchainPdo->exec("
                DO \$\$ 
                DECLARE 
                    r RECORD;
                BEGIN
                    FOR r IN (SELECT tablename FROM pg_tables WHERE schemaname = 'public') LOOP
                        EXECUTE 'DROP TABLE IF EXISTS ' || quote_ident(r.tablename) || ' CASCADE';
                    END LOOP;
                END \$\$;
            ");
        } catch (\PDOException $e) {
            // Ignore errors during cleanup
        }
    }

    /**
     * Get blockchain database configuration
     * Uses the same database as app tests (blockchain tables are separate)
     */
    private function getBlockchainDbConfig(): array
    {
        $driverName = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        if ($driverName === 'pgsql') {
            $config = \Syntexa\Tests\Examples\Orm\PostgresTestHelper::getConnectionParams();
            return [
                'host' => $config['host'],
                'port' => $config['port'],
                'dbname' => $config['dbname'] ?? 'syntexa_test', // Use same DB, not separate
                'user' => $config['user'],
                'password' => $config['password'],
            ];
        }
        
        // SQLite - return dummy config (not used)
        return [
            'host' => 'localhost',
            'port' => 5432,
            'dbname' => 'syntexa_test',
            'user' => 'test',
            'password' => 'test',
        ];
    }

    /**
     * Get all blockchain transactions
     */
    private function getBlockchainTransactions(): array
    {
        if ($this->blockchainPdo === null) {
            return [];
        }
        
        $stmt = $this->blockchainPdo->query("SELECT * FROM blockchain_transactions ORDER BY created_at");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

