<?php

declare(strict_types=1);

namespace Syntexa\Tests;

use Syntexa\Core\Application;
use Syntexa\Core\Request;
use Syntexa\Core\Response;

/**
 * Test client for making HTTP requests to the application
 * 
 * Similar to Symfony's Client, but works directly with Application::handleRequest()
 * without requiring a real Swoole server.
 */
class TestClient
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Make a GET request
     */
    public function request(string $method, string $uri, array $parameters = [], array $files = [], array $server = [], ?string $content = null, bool $changeHistory = true): Response
    {
        // Parse URI
        $parsedUri = parse_url($uri);
        $path = $parsedUri['path'] ?? '/';
        $query = [];
        if (isset($parsedUri['query'])) {
            parse_str($parsedUri['query'], $query);
        }
        $query = array_merge($query, $parameters);

        // Build headers
        $headers = $this->buildHeaders($server);

        // Build server variables
        $serverVars = array_merge([
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'PATH_INFO' => $path,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '9501',
            'HTTP_HOST' => 'localhost:9501',
            'HTTPS' => 'off',
        ], $server);

        // Create Request object
        $request = new Request(
            method: $method,
            uri: $uri,
            headers: $headers,
            query: $query,
            post: $method === 'POST' ? $parameters : [],
            server: $serverVars,
            cookies: $server['HTTP_COOKIE'] ?? [],
            content: $content
        );

        // Handle request
        return $this->app->handleRequest($request);
    }

    /**
     * Make a GET request
     */
    public function get(string $uri, array $parameters = [], array $files = [], array $server = [], ?string $content = null, bool $changeHistory = true): Response
    {
        return $this->request('GET', $uri, $parameters, $files, $server, $content, $changeHistory);
    }

    /**
     * Make a POST request
     */
    public function post(string $uri, array $parameters = [], array $files = [], array $server = [], ?string $content = null, bool $changeHistory = true): Response
    {
        return $this->request('POST', $uri, $parameters, $files, $server, $content, $changeHistory);
    }

    /**
     * Make a PUT request
     */
    public function put(string $uri, array $parameters = [], array $files = [], array $server = [], ?string $content = null, bool $changeHistory = true): Response
    {
        return $this->request('PUT', $uri, $parameters, $files, $server, $content, $changeHistory);
    }

    /**
     * Make a DELETE request
     */
    public function delete(string $uri, array $parameters = [], array $files = [], array $server = [], ?string $content = null, bool $changeHistory = true): Response
    {
        return $this->request('DELETE', $uri, $parameters, $files, $server, $content, $changeHistory);
    }

    /**
     * Build headers from server variables
     */
    private function buildHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }
}

