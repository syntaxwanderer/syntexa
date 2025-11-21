<?php

declare(strict_types=1);

namespace Syntexa\Core\Queue;

use Syntexa\Core\Queue\Message\QueuedHandlerMessage;
use Syntexa\Core\Support\DtoSerializer;

class QueueDispatcher
{
    public static function enqueue(array $handlerMeta, object $requestDto, object $responseDto): void
    {
        $transportName = $handlerMeta['transport'] ?? null;
        if (!$transportName) {
            $transportName = QueueConfig::defaultTransport();
        }

        $queueName = $handlerMeta['queue'] ?? null;
        if (!$queueName) {
            $queueName = QueueConfig::defaultQueueName($handlerMeta['for'] ?? get_class($requestDto));
        }

        $message = new QueuedHandlerMessage(
            handlerClass: $handlerMeta['class'],
            requestClass: get_class($requestDto),
            responseClass: get_class($responseDto),
            requestPayload: DtoSerializer::toArray($requestDto),
            responsePayload: DtoSerializer::toArray($responseDto),
        );

        $transport = QueueTransportRegistry::create($transportName);
        $transport->publish($queueName, $message->toJson());

        echo "📨 Queued handler {$handlerMeta['class']} via {$transportName} ({$queueName})\n";
    }
}

