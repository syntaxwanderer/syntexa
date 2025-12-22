<?php

declare(strict_types=1);

namespace Syntexa\Orm\Blockchain;

/**
 * Blockchain Publisher
 * 
 * Publishes blockchain transactions to RabbitMQ (async, non-blocking).
 */
class BlockchainPublisher
{
    private ?\AMQPConnection $connection = null;
    private ?\AMQPChannel $channel = null;

    public function __construct(
        private readonly BlockchainConfig $config,
        private readonly ?BlockchainStorage $storage = null,
    ) {}

    /**
     * Publish transaction to blockchain (async, non-blocking)
     * 
     * CRITICAL: This must be called AFTER database commit succeeds.
     * Never publish before commit to prevent orphaned transactions.
     */
    public function publish(BlockchainTransaction $transaction): void
    {
        if (!$this->config->enabled) {
            return; // Blockchain disabled
        }

        if ($this->config->hasRabbitMQ()) {
            // Publish to RabbitMQ exchange (non-blocking)
            $this->publishToRabbitMQ($transaction);
        } else {
            // Local mode - add to mempool directly
            $this->addToMempool($transaction);
        }
    }

    /**
     * Publish to RabbitMQ fanout exchange
     */
    private function publishToRabbitMQ(BlockchainTransaction $transaction): void
    {
        // ext-amqp required
        if (!class_exists(\AMQPConnection::class)) {
            throw new \RuntimeException('Blockchain RabbitMQ publishing requires the ext-amqp extension.');
        }

        $this->ensureConnection();

        $channel = $this->channel;
        if ($channel === null) {
            return;
        }

        // Declare fanout exchange for blockchain
        $exchange = new \AMQPExchange($channel);
        $exchange->setName($this->config->rabbitmqExchange ?? 'syntexa_blockchain');
        $exchange->setType(\defined('AMQP_EX_TYPE_FANOUT') ? AMQP_EX_TYPE_FANOUT : 'fanout');
        $exchange->setFlags(\defined('AMQP_DURABLE') ? AMQP_DURABLE : 2);
        $exchange->declareExchange();

        // Publish message (persistent)
        $payload = $transaction->toJson();
        $exchange->publish(
            $payload,
            '', // fanout - routing key is ignored
            \defined('AMQP_NOPARAM') ? AMQP_NOPARAM : 0,
            ['delivery_mode' => 2]
        );
    }

    /**
     * Ensure RabbitMQ connection & channel are initialized
     */
    private function ensureConnection(): void
    {
        if ($this->connection instanceof \AMQPConnection && $this->channel instanceof \AMQPChannel) {
            return;
        }

        $host = $this->config->rabbitmqHost ?? 'localhost';
        $port = $this->config->rabbitmqPort ?? 5672;
        $user = $this->config->rabbitmqUser ?? 'guest';
        $password = $this->config->rabbitmqPassword ?? 'guest';
        $vhost = $this->config->rabbitmqVhost ?? '/';

        $connection = new \AMQPConnection([
            'host' => $host,
            'port' => $port,
            'login' => $user,
            'password' => $password,
            'vhost' => $vhost,
        ]);

        $connection->connect();
        $channel = new \AMQPChannel($connection);

        $this->connection = $connection;
        $this->channel = $channel;
    }

    /**
     * Add to local mempool (to be implemented)
     */
    private function addToMempool(BlockchainTransaction $transaction): void
    {
        // Fallback/local mode: append directly to blockchain storage if available
        if ($this->storage) {
            $this->storage->appendTransaction($transaction);
        }
    }
}

