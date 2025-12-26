<?php

namespace Syntexa\Inspector;

use Swoole\Coroutine\Channel;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Syntexa\Inspector\Storage\CircularBuffer;

class InspectorModule
{
    private $storage;
    /** @var Channel[] */
    private array $channels = [];
    private ?\Swoole\Http\Server $server = null;
    private int $workerId = -1;

    public function __construct($storage = null)
    {
        $this->storage = $storage ?: new CircularBuffer(50);
    }

    public function setServer(\Swoole\Http\Server $server, int $workerId): void
    {
        $this->server = $server;
        $this->workerId = $workerId;
    }

    public function record(string $type, array $payload): void
    {
        $id = uniqid('evt_', true);
        
        // Collect segments from current coroutine context
        $segments = [];
        if (class_exists('Swoole\Coroutine')) {
            $context = \Swoole\Coroutine::getContext();
            if ($context && isset($context['inspector_segments'])) {
                $segments = $context['inspector_segments'];
                unset($context['inspector_segments']); // Clear for next request in same worker
            }
        }

        $entry = [
            'id' => $id,
            'type' => $type,
            'timestamp' => microtime(true),
            'payload' => $payload,
            'segments' => $segments,
        ];

        echo "[Inspector] Recording event {$entry['id']} in worker {$this->workerId} with " . count($segments) . " segments\n";
        $this->storage->add($entry);
        $this->broadcast($entry);

        // Broadcast to other workers
        if ($this->server && $this->workerId !== -1) {
            $message = [
                'target' => 'inspector',
                'event' => $entry
            ];
            $workerNum = $this->server->setting['worker_num'];
            for ($i = 0; $i < $workerNum; $i++) {
                if ($i === $this->workerId) continue;
                $this->server->sendMessage($message, $i);
            }
        }
    }

    public function addSegment(string $type, array $payload): void
    {
        if (!class_exists('Swoole\Coroutine')) {
            return;
        }

        $context = \Swoole\Coroutine::getContext();
        if (!$context) {
            return;
        }

        if (!isset($context['inspector_segments'])) {
            $context['inspector_segments'] = [];
        }

        $context['inspector_segments'][] = [
            'type' => $type,
            'timestamp' => microtime(true),
            'payload' => $payload
        ];
    }

    public function onPipeMessage(array $message): void
    {
        if (($message['target'] ?? '') === 'inspector' && isset($message['event'])) {
            echo "[Inspector] Received pipe message for event {$message['event']['id']} in worker {$this->workerId}\n";
            $this->broadcast($message['event']);
        }
    }

    public function getHistory(): array
    {
        return $this->storage->getAll();
    }

    public function handleStream(Request $request, Response $response): void
    {
        $response->header('Content-Type', 'text/event-stream');
        $response->header('Cache-Control', 'no-cache');
        $response->header('Connection', 'keep-alive');
        $response->header('X-Accel-Buffering', 'no');
        $response->header('Access-Control-Allow-Origin', '*'); // Allow cross-origin SSE

        // Send initial history
        $history = $this->storage->getAll();
        $response->write("event: history\ndata: " . json_encode($history) . "\n\n");

        // Create a channel for this client
        $channel = new Channel(10);
        $channelId = spl_object_id($channel);
        $this->channels[$channelId] = $channel;

        // Keep connection open
        while (true) {
            $event = $channel->pop(5.0); // 5 seconds timeout to send ping
            
            if ($event === false) {
                 // Timeout, send ping to keep connection alive
                 if (!$response->write(":\n\n")) {
                     break; // Client disconnected
                 }
                 continue;
            }

            $data = json_encode($event);
            if (!$response->write("event: new_entry\ndata: {$data}\n\n")) {
                break; // Client disconnected
            }
        }

        // Cleanup
        unset($this->channels[$channelId]);
    }

    private function broadcast(array $entry): void
    {
        foreach ($this->channels as $id => $channel) {
            if ($channel->isFull()) {
                continue; // Skip if client is slow
            }
            $channel->push($entry);
        }
    }
}
