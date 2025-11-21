<?php

declare(strict_types=1);

namespace Syntexa\Core\Queue;

use Syntexa\Core\Environment;

class QueueConfig
{
    public static function defaultTransport(): string
    {
        return Environment::getEnvValue('SYN_QUEUE_TRANSPORT', 'in-memory') ?? 'in-memory';
    }

    public static function defaultQueueName(string $requestClass): string
    {
        $normalized = strtolower(str_replace('\\', '.', $requestClass));

        return Environment::getEnvValue('SYN_QUEUE_DEFAULT', $normalized) ?? $normalized;
    }
}

