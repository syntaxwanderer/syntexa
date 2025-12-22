<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm\Blockchain;

use PDO;
use Syntexa\Orm\Blockchain\BlockchainConfig;
use Syntexa\Orm\Blockchain\BlockchainConsumer;
use Syntexa\Orm\Blockchain\BlockchainPublisher;
use Syntexa\Orm\Blockchain\BlockchainStorage;
use Syntexa\Orm\Blockchain\BlockchainTransaction;
use Syntexa\Orm\Entity\EntityManager;

/**
 * Node Simulator
 * 
 * Simulates a blockchain node with:
 * - Own database connection (isolated)
 * - RabbitMQ connection (shared exchange)
 * - Blockchain storage
 * - Publisher/Consumer capabilities
 * 
 * Used for multi-node blockchain testing.
 */
class NodeSimulator
{
    private PDO $pdo;
    private BlockchainConfig $config;
    private BlockchainStorage $storage;
    private BlockchainPublisher $publisher;
    private ?\AMQPConnection $rabbitmqConnection = null;
    private ?\AMQPChannel $rabbitmqChannel = null;
    private ?\AMQPQueue $rabbitmqQueue = null;
    private array $consumedTransactions = [];

    public function __construct(
        public readonly string $nodeId,
        private readonly array $dbConfig,
        private readonly array $rabbitmqConfig,
        private readonly array $participants = [],
    ) {
        // Create blockchain config for this node
        $this->config = new BlockchainConfig(
            enabled: true,
            dbHost: $dbConfig['host'],
            dbPort: $dbConfig['port'],
            dbName: $dbConfig['dbname'],
            dbUser: $dbConfig['user'],
            dbPassword: $dbConfig['password'],
            participants: $participants,
            nodeId: $nodeId,
            rabbitmqHost: $rabbitmqConfig['host'],
            rabbitmqPort: $rabbitmqConfig['port'],
            rabbitmqUser: $rabbitmqConfig['user'],
            rabbitmqPassword: $rabbitmqConfig['password'],
            rabbitmqExchange: $rabbitmqConfig['exchange'],
            rabbitmqVhost: $rabbitmqConfig['vhost'] ?? '/',
            blockSize: 10, // Small blocks for testing
            blockTimeLimit: 5,
            mempoolMaxSize: 100,
            proposerInterval: 2,
            consensusTimeout: 10,
        );

        // Create database connection
        $this->pdo = new PDO(
            sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['dbname']
            ),
            $dbConfig['user'],
            $dbConfig['password']
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create blockchain storage
        $this->storage = new BlockchainStorage($this->config);
        
        // Inject PDO into storage (for testing)
        $reflection = new \ReflectionClass($this->storage);
        $connectionProperty = $reflection->getProperty('connection');
        $connectionProperty->setAccessible(true);
        $connectionProperty->setValue($this->storage, $this->pdo);

        // Ensure schema exists
        $ensureSchemaMethod = $reflection->getMethod('ensureSchema');
        $ensureSchemaMethod->setAccessible(true);
        $ensureSchemaMethod->invoke($this->storage, $this->pdo);

        // Create publisher
        $this->publisher = new BlockchainPublisher($this->config, $this->storage);
    }

    /**
     * Get node's database connection
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Get node's blockchain config
     */
    public function getConfig(): BlockchainConfig
    {
        return $this->config;
    }

    /**
     * Get node's blockchain storage
     */
    public function getStorage(): BlockchainStorage
    {
        return $this->storage;
    }

    /**
     * Get node's blockchain publisher
     */
    public function getPublisher(): BlockchainPublisher
    {
        return $this->publisher;
    }

    /**
     * Publish transaction to RabbitMQ
     */
    public function publishTransaction(BlockchainTransaction $transaction): void
    {
        $this->publisher->publish($transaction);
    }

    /**
     * Consume one transaction from RabbitMQ (non-blocking)
     * 
     * Returns true if transaction was consumed, false if queue is empty
     */
    public function consumeOneTransaction(float $timeout = 1.0): bool
    {
        if (!class_exists(\AMQPConnection::class)) {
            throw new \RuntimeException('ext-amqp is required for RabbitMQ consumption');
        }

        $this->ensureRabbitMQConnection();

        if ($this->rabbitmqQueue === null) {
            return false;
        }

        // Try to get one message (non-blocking)
        $envelope = $this->rabbitmqQueue->get(\AMQP_NOPARAM);
        
        if ($envelope === false) {
            return false; // No message available
        }

        try {
            $body = $envelope->getBody();
            $tx = BlockchainTransaction::fromJson($body);
            
            // Store transaction in blockchain
            $this->storage->appendTransaction($tx);
            
            // Track consumed transaction
            $this->consumedTransactions[] = $tx;
            
            // Acknowledge message
            $this->rabbitmqQueue->ack($envelope->getDeliveryTag());
            
            return true;
        } catch (\Throwable $e) {
            // Reject message on error
            $this->rabbitmqQueue->nack($envelope->getDeliveryTag());
            throw $e;
        }
    }

    /**
     * Consume all available transactions from RabbitMQ
     * 
     * Returns number of consumed transactions
     */
    public function consumeAllTransactions(float $timeout = 1.0): int
    {
        $consumed = 0;
        $startTime = microtime(true);
        
        while ((microtime(true) - $startTime) < $timeout) {
            if ($this->consumeOneTransaction(0.1)) {
                $consumed++;
            } else {
                // No more messages, wait a bit
                usleep(10000); // 10ms
            }
        }
        
        return $consumed;
    }

    /**
     * Get all transactions stored in this node's blockchain
     */
    public function getBlockchainTransactions(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM blockchain_transactions ORDER BY created_at");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all transactions consumed by this node
     */
    public function getConsumedTransactions(): array
    {
        return $this->consumedTransactions;
    }

    /**
     * Get last finalized block hash from validator state
     */
    public function getLastFinalizedBlockHash(): ?string
    {
        $stmt = $this->pdo->query("SELECT last_finalized_block_hash FROM validator_state WHERE id = 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['last_finalized_block_hash'] : null;
    }

    /**
     * Get last finalized block height
     */
    public function getLastFinalizedHeight(): int
    {
        $stmt = $this->pdo->query("SELECT last_finalized_height FROM validator_state WHERE id = 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int) $result['last_finalized_height'] : 0;
    }

    /**
     * Cleanup blockchain database (drop all tables)
     */
    public function cleanup(): void
    {
        try {
            $this->pdo->exec("
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
        
        $this->consumedTransactions = [];
    }

    /**
     * Close RabbitMQ connection
     */
    public function close(): void
    {
        if ($this->rabbitmqConnection !== null) {
            $this->rabbitmqConnection->disconnect();
            $this->rabbitmqConnection = null;
            $this->rabbitmqChannel = null;
            $this->rabbitmqQueue = null;
        }
    }

    /**
     * Ensure RabbitMQ connection is established
     */
    private function ensureRabbitMQConnection(): void
    {
        if ($this->rabbitmqConnection instanceof \AMQPConnection) {
            return;
        }

        $connection = new \AMQPConnection([
            'host' => $this->rabbitmqConfig['host'],
            'port' => $this->rabbitmqConfig['port'],
            'login' => $this->rabbitmqConfig['user'],
            'password' => $this->rabbitmqConfig['password'],
            'vhost' => $this->rabbitmqConfig['vhost'] ?? '/',
        ]);

        $connection->connect();
        $channel = new \AMQPChannel($connection);

        // Declare fanout exchange
        $exchange = new \AMQPExchange($channel);
        $exchange->setName($this->rabbitmqConfig['exchange']);
        $exchange->setType(\defined('AMQP_EX_TYPE_FANOUT') ? AMQP_EX_TYPE_FANOUT : 'fanout');
        $exchange->setFlags(\defined('AMQP_DURABLE') ? AMQP_DURABLE : 2);
        $exchange->declareExchange();

        // Declare queue for this node
        $queue = new \AMQPQueue($channel);
        $queueName = 'blockchain.' . $this->nodeId;
        $queue->setName($queueName);
        $queue->setFlags(\defined('AMQP_DURABLE') ? AMQP_DURABLE : 2);
        $queue->declareQueue();
        $queue->bind($exchange->getName());

        $this->rabbitmqConnection = $connection;
        $this->rabbitmqChannel = $channel;
        $this->rabbitmqQueue = $queue;
    }
}

