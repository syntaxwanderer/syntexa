<?php

declare(strict_types=1);

namespace Syntexa\Core\Queue;

use Syntexa\Core\Queue\Message\QueuedHandlerMessage;
use Syntexa\Core\Support\DtoSerializer;

class QueueWorker
{
    public function run(?string $transportName, ?string $queueName = null): void
    {
        $transportName = $transportName ?: QueueConfig::defaultTransport();
        $queueName = $queueName ?: QueueConfig::defaultQueueName('default');

        $transport = QueueTransportRegistry::create($transportName);

        echo "👷  Queue worker started (transport={$transportName}, queue={$queueName})\n";

        $transport->consume($queueName, function (string $payload): void {
            $this->processPayload($payload);
        });
    }

    private function processPayload(string $payload): void
    {
        try {
            $message = QueuedHandlerMessage::fromJson($payload);
        } catch (\Throwable $e) {
            echo "❌ Failed to decode queued message: {$e->getMessage()}\n";
            return;
        }

        $handlerClass = $message->handlerClass;
        if (!class_exists($handlerClass)) {
            echo "⚠️  Handler {$handlerClass} not found\n";
            return;
        }

        $request = $this->hydrateDto($message->requestClass, $message->requestPayload);
        $response = $this->hydrateDto($message->responseClass, $message->responsePayload);

        $handler = new $handlerClass();
        if (!method_exists($handler, 'handle')) {
            echo "⚠️  Handler {$handlerClass} has no handle() method\n";
            return;
        }

        $handler->handle($request, $response);
        echo "✅ Async handler executed: {$handlerClass}\n";
    }

    private function hydrateDto(string $class, array $payload): object
    {
        $dto = class_exists($class) ? new $class() : new \stdClass();

        return DtoSerializer::hydrate($dto, $payload);
    }
}

