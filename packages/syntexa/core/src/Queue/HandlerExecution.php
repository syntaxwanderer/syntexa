<?php

declare(strict_types=1);

namespace Syntexa\Core\Queue;

enum HandlerExecution: string
{
    case Sync = 'sync';
    case Async = 'async';

    public static function normalize(HandlerExecution|string|null $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return self::Sync;
        }

        $normalized = strtolower((string) $value);

        return match ($normalized) {
            'async', 'asynchronous', 'queue', 'queued' => self::Async,
            default => self::Sync,
        };
    }
}

