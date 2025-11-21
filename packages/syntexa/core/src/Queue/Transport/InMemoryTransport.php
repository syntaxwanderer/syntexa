<?php

declare(strict_types=1);

namespace Syntexa\Core\Queue\Transport;

use Syntexa\Core\Queue\QueueTransportInterface;

class InMemoryTransport implements QueueTransportInterface
{
    /**
     * @var array<string, array<int, string>>
     */
    private static array $queues = [];

    public function publish(string $queueName, string $payload): void
    {
        $queueName = $this->normalizeQueue($queueName);
        self::$queues[$queueName][] = $payload;
    }

    public function consume(string $queueName, callable $callback): void
    {
        $queueName = $this->normalizeQueue($queueName);
        echo "📥 [in-memory] Listening on queue '{$queueName}'...\n";
        while (true) {
            if (!empty(self::$queues[$queueName])) {
                $payload = array_shift(self::$queues[$queueName]);
                $callback($payload);
            } else {
                usleep(250000);
            }
        }
    }

    private function normalizeQueue(string $queue): string
    {
        return strtolower($queue ?: 'default');
    }
}

