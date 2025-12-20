<?php

declare(strict_types=1);

namespace Syntexa\UserDomain\Domain\Service;

/**
 * Domain service for tracking login page analytics
 * 
 * This service contains business logic for login analytics.
 * Domain services are part of the domain layer and are independent
 * of infrastructure concerns (frameworks, databases, etc.).
 * 
 * This is a request-scoped service (new instance per request)
 */
class LoginAnalyticsService
{
    private string $projectRoot;

    public function __construct()
    {
        $this->projectRoot = $this->getProjectRoot();
    }

    /**
     * Log login page visit
     */
    public function logPageVisit(string $ip, string $userAgent): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = sprintf(
            "[LOGIN ANALYTICS] Page visited at %s | IP: %s | User-Agent: %s",
            $timestamp,
            $ip,
            $userAgent
        );

        $logFile = $this->projectRoot . '/var/log/login-analytics.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
    }

    /**
     * Get visit statistics (simple example)
     */
    public function getVisitCount(): int
    {
        $logFile = $this->projectRoot . '/var/log/login-analytics.log';
        if (!file_exists($logFile)) {
            return 0;
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return count($lines);
    }

    /**
     * Get project root directory
     */
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

        return dirname(__DIR__, 8);
    }
}

