<?php

namespace Syntexa\Inspector\Watchers;

use Syntexa\Inspector\InspectorModule;
use Syntexa\Core\Request;
use Syntexa\Core\Response;

class RequestWatcher
{
    private InspectorModule $inspector;

    public function __construct(InspectorModule $inspector)
    {
        $this->inspector = $inspector;
    }

    public function startRequest(Request $request): array
    {
        return [
            'start_time' => microtime(true),
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
            'headers' => $request->headers,
            'query' => $request->query,
            'body' => $request->post, // Be careful with large bodies
        ];
    }

    public function endRequest(Request $request, Response $response, array $context): void
    {
        $duration = (microtime(true) - $context['start_time']) * 1000;

        $payload = [
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
            'status' => $response->getStatusCode(),
            'duration' => round($duration, 2),
            'response_headers' => $response->getHeaders(),
            'memory' => memory_get_usage(true),
            'request_headers' => $context['headers'],
            'query' => $context['query'],
            // 'body' => $context['body'], // Enable if needed
        ];

        $this->inspector->record('http_request', $payload);
    }
}
