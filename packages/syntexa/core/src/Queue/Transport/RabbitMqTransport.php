<?php

declare(strict_types=1);

namespace Syntexa\Core\Queue\Transport;

use Syntexa\Core\Queue\QueueTransportInterface;

class RabbitMqTransport implements QueueTransportInterface
{
    private \AMQPConnection $connection;
    private \AMQPChannel $channel;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $password,
        private readonly string $vhost,
    ) {
        if (!class_exists(\AMQPConnection::class)) {
            throw new \RuntimeException('RabbitMQ transport requires the ext-amqp extension.');
        }

        $this->connection = new \AMQPConnection([
            'host' => $this->host,
            'port' => $this->port,
            'login' => $this->user,
            'password' => $this->password,
            'vhost' => $this->vhost,
        ]);
        $this->connection->connect();
        $this->channel = new \AMQPChannel($this->connection);
    }

    public function publish(string $queueName, string $payload): void
    {
        $queue = $this->ensureQueue($queueName);
        $exchange = new \AMQPExchange($queue->getChannel());
        $exchange->setType(\defined('AMQP_EX_TYPE_DIRECT') ? AMQP_EX_TYPE_DIRECT : 'direct');
        $exchange->setName('');
        $exchange->publish(
            $payload,
            $queue->getName(),
            \defined('AMQP_NOPARAM') ? AMQP_NOPARAM : 0,
            ['delivery_mode' => 2]
        );
    }

    public function consume(string $queueName, callable $callback): void
    {
        $queue = $this->ensureQueue($queueName);
        $queue->consume(function (\AMQPEnvelope $envelope, \AMQPQueue $queue) use ($callback) {
            $callback($envelope->getBody());
            $queue->ack($envelope->getDeliveryTag());
            return true;
        });
    }

    private function ensureQueue(string $queueName): \AMQPQueue
    {
        $queue = new \AMQPQueue($this->channel);
        $queue->setName($queueName);
        $queue->setFlags(\defined('AMQP_DURABLE') ? AMQP_DURABLE : 2);
        $queue->declareQueue();

        return $queue;
    }
}

