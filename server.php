<?php

declare(strict_types=1);

echo "Starting Syntexa server...\n";

use Swoole\Http\Server;
use Syntexa\Core\Application;
use Syntexa\Core\ErrorHandler;
use Syntexa\Core\Request;

/**
 * Swoole server entry point
 * This file starts the Swoole HTTP server
 */

// Load Composer autoloader
echo "Loading autoloader...\n";
require_once __DIR__ . '/vendor/autoload.php';
echo "Autoloader loaded\n";

// Check if Swoole is available
if (!extension_loaded('swoole')) {
    die("Swoole extension is required but not installed.\n");
}

// Create application to get environment
echo "Creating application...\n";
$app = new Application();
echo "Application created\n";
$env = $app->getEnvironment();
echo "Environment loaded\n";

// Configure error handling
echo "Configuring error handling...\n";
ErrorHandler::configure($env);
echo "Error handling configured\n";

// Create Swoole HTTP server with environment configuration
echo "Creating Swoole server on {$env->swooleHost}:{$env->swoolePort}...\n";
$server = new Server($env->swooleHost, $env->swoolePort);
echo "Swoole server created\n";

// Server configuration from environment
$pidFile = __DIR__ . '/var/swoole.pid';
@mkdir(dirname($pidFile), 0777, true);
$server->set([
    'worker_num' => $env->swooleWorkerNum,
    'max_request' => $env->swooleMaxRequest,
    'enable_coroutine' => true,
    'max_coroutine' => $env->swooleMaxCoroutine,
    'log_file' => $env->swooleLogFile,
    'log_level' => $env->swooleLogLevel,
    'pid_file' => $pidFile,
]);

// Server events
$server->on("start", function ($server) use ($env) {
    echo "Syntexa Framework - Swoole Mode\n";
    echo "Server started at http://{$env->swooleHost}:{$env->swoolePort}\n";
    echo "Mode: " . ($env->isDev() ? 'development' : 'production') . "\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Swoole Version: " . swoole_version() . "\n";
    echo "Workers: {$env->swooleWorkerNum}\n";
    echo "Max Requests: {$env->swooleMaxRequest}\n";
});

$server->on("request", function ($request, $response) use ($env, $app) {
    $path = $request->server['request_uri'] ?? '/';
    $method = $request->server['request_method'] ?? 'GET';
    
    // Ensure response is always sent
    $responseSent = false;
    
    try {
        echo "📥 Incoming request: {$method} {$path}\n";
    // Set CORS headers from environment
    $response->header("Access-Control-Allow-Origin", $env->corsAllowOrigin);
    $response->header("Access-Control-Allow-Methods", $env->corsAllowMethods);
    $response->header("Access-Control-Allow-Headers", $env->corsAllowHeaders);
    
    if ($env->corsAllowCredentials) {
        $response->header("Access-Control-Allow-Credentials", "true");
    }
    
    // Handle preflight requests
        if ($method === 'OPTIONS') {
            echo "✈️  OPTIONS preflight request\n";
        $response->status(200);
        $response->end();
        return;
    }
    
        // Content-Type will be set per response type (json/html/file). Do not force here.
    
    // Create Request object from Swoole request
    $syntexaRequest = Request::create($request);
        echo "✅ Created Syntexa Request: {$syntexaRequest->getPath()} ({$syntexaRequest->getMethod()})\n";
    
    // Handle request
    $syntexaResponse = $app->handleRequest($syntexaRequest);
        echo "✅ Got response: {$syntexaResponse->getStatusCode()}\n";
    
    // Set status code
    $response->status($syntexaResponse->getStatusCode());
    
    // Set headers
    foreach ($syntexaResponse->getHeaders() as $name => $value) {
        $response->header($name, $value);
    }
    
    // Output response
        $content = $syntexaResponse->getContent();
        echo "📤 Sending response: " . strlen($content) . " bytes\n";
        $response->end($content);
        $responseSent = true;
        echo "✅ Response sent\n";
    } catch (\Throwable $e) {
        echo "❌ Error in request handler: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        
        if (!$responseSent) {
            try {
                $response->status(500);
                $response->header("Content-Type", "application/json");
                $errorResponse = json_encode([
                    'error' => 'Internal Server Error',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                $response->end($errorResponse);
                $responseSent = true;
            } catch (\Throwable $sendError) {
                echo "❌ Failed to send error response: " . $sendError->getMessage() . "\n";
            }
        }
    } finally {
        // Reset request-scoped container cache after each request
        // This ensures no data leakage between requests in Swoole
        // Note: PHP-DI uses factory functions for request-scoped services,
        // but we also reset our wrapper cache for extra safety
        if (isset($app)) {
            $app->getRequestScopedContainer()->reset();
        }
        
        // Final safety net - ensure response is always sent
        if (!$responseSent) {
            try {
                $response->status(500);
                $response->header("Content-Type", "text/plain");
                $response->end("Internal Server Error - No response generated");
            } catch (\Throwable $e) {
                // Last resort - silently fail
            }
        }
    }
});

// Start the server
$server->start();
