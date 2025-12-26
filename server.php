<?php

declare(strict_types=1);

echo "Starting Syntexa server...\n";

use Swoole\Http\Server;
use Syntexa\Core\Application;
use Syntexa\Core\ErrorHandler;
use Syntexa\Core\Request;
use Syntexa\Inspector\InspectorModule;
use Syntexa\Inspector\Watchers\RequestWatcher;
use Syntexa\Inspector\Storage\SharedCircularBuffer;

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

// Initialize DI container first (this initializes ConnectionPool if needed)
echo "Initializing DI container...\n";
\Syntexa\Core\Container\ContainerFactory::create();
echo "DI container initialized\n";

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

// Initialize Inspector Shared Storage
echo "Initializing Inspector storage...\n";
$sharedStorage = new SharedCircularBuffer(50);
$inspector = new InspectorModule($sharedStorage);
\Syntexa\Inspector\Profiler::setInspector($inspector);
$requestWatcher = new RequestWatcher($inspector);
echo "Inspector initialized\n";

// Create Swoole HTTP server with environment configuration
echo "Creating Swoole server on {$env->swooleHost}:{$env->swoolePort}...\n";
$server = new Server($env->swooleHost, $env->swoolePort);
echo "Swoole server created\n";

// Server configuration from environment
$appNameSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $env->appName)));
$pidFile = __DIR__ . "/var/swoole-{$appNameSlug}.pid";
$statsFile = __DIR__ . "/var/server-stats-{$appNameSlug}.json";
$swooleStatsFile = __DIR__ . "/var/swoole-stats-{$appNameSlug}.json";
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

// Initialize stats
$stats = [
    'requests' => 0,
    'errors' => 0,
    'start_time' => time(),
    'uptime' => 0,
];
file_put_contents($statsFile, json_encode($stats));

// Server events
$server->on("start", function ($server) use ($env, $swooleStatsFile) {
    echo "Syntexa Framework - Swoole Mode\n";
    echo "Server started at http://{$env->swooleHost}:{$env->swoolePort}\n";
    echo "Mode: " . ($env->isDev() ? 'development' : 'production') . "\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Swoole Version: " . swoole_version() . "\n";
    echo "Workers: {$env->swooleWorkerNum}\n";
    echo "Max Requests: {$env->swooleMaxRequest}\n";
    
    // Periodically update Swoole stats
    \Swoole\Timer::tick(2000, function () use ($server, $swooleStatsFile) {
        $stats = $server->stats();
        // Add memory stats (Swoole stats() doesn't include memory)
        $stats['memory_total'] = memory_get_usage(true);
        $stats['memory_peak'] = memory_get_peak_usage(true);
        file_put_contents($swooleStatsFile, json_encode($stats));
    });
});

$server->on("workerStart", function ($server, $workerId) use ($inspector) {
    if ($workerId < $server->setting['worker_num']) {
        $inspector->setServer($server, $workerId);
    }
});

$server->on("pipeMessage", function ($server, $srcWorkerId, $message) use ($inspector) {
    $inspector->onPipeMessage($message);
});

$server->on("request", function ($request, $response) use ($env, $app, $statsFile, $swooleStatsFile, $server, $inspector, $requestWatcher) {
    $path = $request->server['request_uri'] ?? '/';
    file_put_contents('/tmp/inspector_debug.log', date('H:i:s') . " Request: $path\n", FILE_APPEND);
    $method = $request->server['request_method'] ?? 'GET';

    // Ensure response is always sent
    $responseSent = false;
    $hasError = false;
    
    try {
        // Set CORS headers from environment
        $response->header("Access-Control-Allow-Origin", $env->corsAllowOrigin);
        $response->header("Access-Control-Allow-Methods", $env->corsAllowMethods);
        $response->header("Access-Control-Allow-Headers", $env->corsAllowHeaders);
        
        if ($env->corsAllowCredentials) {
            $response->header("Access-Control-Allow-Credentials", "true");
        }
    
        // Handle preflight requests
        if ($method === 'OPTIONS') {
            echo "✈️  OPTIONS preflight request for $path\n";
            $response->status(200);
            $response->end();
            return;
        }

        // Handle Inspector Stream
        if ($path === '/_inspector/stream') {
            $inspector->handleStream($request, $response);
            return;
        }

        // Handle Inspector UI
        if ($path === '/_inspector') {
            $response->header("Content-Type", "text/html");
            $template = file_get_contents(__DIR__ . '/packages/syntexa/inspector/src/UI/dashboard.html');
            
            // Parse cluster config
            $clusterEnv = $app->getEnvironment()->getEnvValue('INSPECTOR_CLUSTER', '');
            $nodes = [];
            if ($clusterEnv) {
                foreach (explode(',', $clusterEnv) as $nodeStr) {
                    [$name, $url] = explode('|', trim($nodeStr), 2);
                    $nodes[] = ['name' => trim($name), 'url' => trim($url)];
                }
            } else {
                $nodes[] = ['name' => 'Local', 'url' => ''];
            }
            
            $configJson = json_encode(['nodes' => $nodes]);
            $content = str_replace('{{ CLUSTER_CONFIG }}', $configJson, $template);
            $response->end($content);
            return;
        }
        
        // Handle /metrics endpoint for Prometheus
        if ($path === '/metrics' && $method === 'GET') {
            $swooleStats = file_exists($swooleStatsFile) ? json_decode(file_get_contents($swooleStatsFile), true) : [];
            $appStats = file_exists($statsFile) ? json_decode(file_get_contents($statsFile), true) : [];
            
            $metrics = [];
            $metrics[] = "# HELP swoole_connections_active Active connections";
            $metrics[] = "# TYPE swoole_connections_active gauge";
            $metrics[] = "swoole_connections_active " . ($swooleStats['connection_num'] ?? 0);
            
            $metrics[] = "# HELP swoole_requests_total Total requests";
            $metrics[] = "# TYPE swoole_requests_total counter";
            $metrics[] = "swoole_requests_total " . ($swooleStats['request_count'] ?? 0);
            
            $metrics[] = "# HELP swoole_workers_active Active workers";
            $metrics[] = "# TYPE swoole_workers_active gauge";
            $metrics[] = "swoole_workers_active " . ($swooleStats['worker_num'] ?? 0);
            
            $metrics[] = "# HELP swoole_workers_idle Idle workers";
            $metrics[] = "# TYPE swoole_workers_idle gauge";
            $metrics[] = "swoole_workers_idle " . ($swooleStats['idle_worker_num'] ?? 0);
            
            $metrics[] = "# HELP swoole_coroutines_active Active coroutines";
            $metrics[] = "# TYPE swoole_coroutines_active gauge";
            $metrics[] = "swoole_coroutines_active " . ($swooleStats['coroutine_num'] ?? 0);
            
            $metrics[] = "# HELP swoole_memory_bytes Memory usage in bytes";
            $metrics[] = "# TYPE swoole_memory_bytes gauge";
            $metrics[] = "swoole_memory_bytes " . ($swooleStats['memory_total'] ?? 0);
            
            $metrics[] = "# HELP swoole_memory_peak_bytes Peak memory usage in bytes";
            $metrics[] = "# TYPE swoole_memory_peak_bytes gauge";
            $metrics[] = "swoole_memory_peak_bytes " . ($swooleStats['memory_peak'] ?? 0);
            
            $metrics[] = "# HELP app_requests_total Total application requests";
            $metrics[] = "# TYPE app_requests_total counter";
            $metrics[] = "app_requests_total " . ($appStats['requests'] ?? 0);
            
            $metrics[] = "# HELP app_errors_total Total application errors";
            $metrics[] = "# TYPE app_errors_total counter";
            $metrics[] = "app_errors_total " . ($appStats['errors'] ?? 0);
            
            $metrics[] = "# HELP app_uptime_seconds Application uptime in seconds";
            $metrics[] = "# TYPE app_uptime_seconds gauge";
            $metrics[] = "app_uptime_seconds " . ($appStats['uptime'] ?? 0);
            
            $response->header("Content-Type", "text/plain; version=0.0.4");
            $response->end(implode("\n", $metrics) . "\n");
            return;
        }

        // Update stats for non-system requests
        $stats = json_decode(file_get_contents($statsFile), true) ?: ['requests' => 0, 'errors' => 0, 'start_time' => time()];
        $stats['requests']++;
        $stats['uptime'] = time() - $stats['start_time'];
        file_put_contents($statsFile, json_encode($stats));

        echo "📥 Incoming request: {$method} {$path}\n";
    
        // Content-Type will be set per response type (json/html/file). Do not force here.
    
    // Create Request object from Swoole request
    $syntexaRequest = Request::create($request);
        echo "✅ Created Syntexa Request: {$syntexaRequest->getPath()} ({$syntexaRequest->getMethod()})\n";
    
    // Start Inspector Watcher
    $requestContext = $requestWatcher->startRequest($syntexaRequest);

    // Handle request
    $syntexaResponse = $app->handleRequest($syntexaRequest);

    // End Inspector Watcher
    $requestWatcher->endRequest($syntexaRequest, $syntexaResponse, $requestContext);

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
        $hasError = true;
        // Update error stats
        $stats = json_decode(file_get_contents($statsFile), true) ?: ['requests' => 0, 'errors' => 0, 'start_time' => time()];
        $stats['errors']++;
        file_put_contents($statsFile, json_encode($stats));
        
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
