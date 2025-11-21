<?php

declare(strict_types=1);

namespace Syntexa\Core\Queue;

interface QueueTransportInterface
{
    public function publish(string $queueName, string $payload): void;

    /**
     * @param callable(string):void $callback
     */
    public function consume(string $queueName, callable $callback): void;
}

