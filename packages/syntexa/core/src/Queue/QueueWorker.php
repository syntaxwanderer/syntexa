<?php

declare(strict_types=1);

namespace Syntexa\Core\Queue;

use Syntexa\Core\Queue\Message\QueuedHandlerMessage;
use Syntexa\Core\Support\DtoSerializer;

class QueueWorker
{
    private string $statsFile;

    public function __construct()
    {
        $this->statsFile = $this->getProjectRoot() . '/var/queue-stats.json';
        @mkdir(dirname($this->statsFile), 0777, true);
        
        // Initialize stats if file doesn't exist
        if (!file_exists($this->statsFile)) {
            file_put_contents($this->statsFile, json_encode([
                'processed' => 0,
                'failed' => 0,
                'start_time' => time(),
            ]));
        }
    }

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

    public function processPayload(string $payload): void
    {
        try {
            $message = QueuedHandlerMessage::fromJson($payload);
        } catch (\Throwable $e) {
            echo "❌ Failed to decode queued message: {$e->getMessage()}\n";
            $this->updateStats('failed');
            return;
        }

        $handlerClass = $message->handlerClass;
        if (!class_exists($handlerClass)) {
            echo "⚠️  Handler {$handlerClass} not found\n";
            $this->updateStats('failed');
            return;
        }

        try {
            $request = $this->hydrateDto($message->requestClass, $message->requestPayload);
            $response = $this->hydrateDto($message->responseClass, $message->responsePayload);

            $handler = new $handlerClass();
            if (!method_exists($handler, 'handle')) {
                echo "⚠️  Handler {$handlerClass} has no handle() method\n";
                $this->updateStats('failed');
                return;
            }
        } catch (\Throwable $e) {
            echo "❌ Error processing payload: {$e->getMessage()}\n";
            $this->updateStats('failed');
            return;
        }

        try {
            $handler->handle($request, $response);
            echo "✅ Async handler executed: {$handlerClass}\n";
            
            // Update stats
            $this->updateStats('processed');
        } catch (\Throwable $e) {
            echo "❌ Error executing handler: {$e->getMessage()}\n";
            $this->updateStats('failed');
        }
    }
    
    private function updateStats(string $type): void
    {
        $stats = json_decode(file_get_contents($this->statsFile), true) ?: [
            'processed' => 0,
            'failed' => 0,
            'start_time' => time(),
        ];
        $stats[$type] = ($stats[$type] ?? 0) + 1;
        file_put_contents($this->statsFile, json_encode($stats));
    }
    
    private function getProjectRoot(): string
    {
        $dir = __DIR__;
        while ($dir !== '/' && $dir !== '') {
            if (file_exists($dir . '/composer.json')) {
                if (is_dir($dir . '/src/modules')) {
                    return $dir;
                }
            }
            $dir = dirname($dir);
        }
        return dirname(__DIR__, 5);
    }


    private function hydrateDto(string $class, array $payload): object
    {
        $dto = class_exists($class) ? new $class() : new \stdClass();

        return DtoSerializer::hydrate($dto, $payload);
    }
}

