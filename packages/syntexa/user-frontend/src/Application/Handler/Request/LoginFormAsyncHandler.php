<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Application\Handler\Request;

use Syntexa\Core\Attributes\AsRequestHandler;
use Syntexa\Core\Handler\HttpHandlerInterface;
use Syntexa\Core\Queue\HandlerExecution;
use Syntexa\Core\Contract\RequestInterface;
use Syntexa\Core\Contract\ResponseInterface;
use Syntexa\UserFrontend\Application\Input\Http\LoginFormRequest;
use Syntexa\UserFrontend\Application\Output\LoginFormResponse;

#[AsRequestHandler(
    for: LoginFormRequest::class,
    execution: HandlerExecution::Async,
    transport: 'rabbitmq',
    queue: 'login.analytics',
    priority: 50
)]
class LoginFormAsyncHandler implements HttpHandlerInterface
{
    /**
     * This handler runs asynchronously via RabbitMQ
     * It logs login page visits for analytics
     * 
     * @param LoginFormRequest $request
     * @param LoginFormResponse $response
     * @return LoginFormResponse
     */
    public function handle(RequestInterface $request, ResponseInterface $response): LoginFormResponse
    {
        /** @var LoginFormRequest $request */
        /** @var LoginFormResponse $response */
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $logMessage = sprintf(
            "[ASYNC HANDLER] Login page visited at %s | IP: %s | User-Agent: %s",
            $timestamp,
            $ip,
            $userAgent
        );
        
        // Log to file (relative to project root)
        $projectRoot = self::getProjectRoot();
        $logFile = $projectRoot . '/var/log/async-handlers.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
        
        // Also output to console for visibility
        echo "📊 " . $logMessage . PHP_EOL;
        
        return $response;
    }

    /**
     * Get project root directory (where composer.json is located)
     */
    private static function getProjectRoot(): string
    {
        $dir = __DIR__;
        while ($dir !== '/' && $dir !== '') {
            if (file_exists($dir . '/composer.json')) {
                // Check if this is the actual project root (has src/modules)
                if (is_dir($dir . '/src/modules')) {
                    return $dir;
                }
            }
            $dir = dirname($dir);
        }
        
        // Fallback: go up 7 levels from Handler/Request
        return dirname(__DIR__, 7);
    }
}

