<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm;

/**
 * Helper for managing PostgreSQL test database in Docker
 */
class PostgresTestHelper
{
    private const CONTAINER_NAME = 'syntexa-postgres-test';
    private const COMPOSE_FILE = __DIR__ . '/../../../docker-compose.test.yml';
    
    // Default test values (fallback if .env not found)
    private const DEFAULT_DB_HOST = 'localhost';
    private const DEFAULT_DB_PORT = 5433;
    private const DEFAULT_DB_NAME = 'syntexa_test';
    private const DEFAULT_DB_USER = 'test';
    private const DEFAULT_DB_PASSWORD = 'test';

    /**
     * Load database configuration from .env file or use defaults
     */
    private static function loadConfig(): array
    {
        $envFile = __DIR__ . '/../../../.env';
        
        // Try to read from .env file
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $config = [];
            
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Parse KEY=VALUE
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    $config[$key] = $value;
                }
            }
            
            $dbName = $config['DB_NAME'] ?? self::DEFAULT_DB_NAME;
            // Append _test suffix to avoid using production database
            if (!str_ends_with($dbName, '_test')) {
                $dbName .= '_test';
            }
            
            return [
                'host' => $config['DB_HOST'] ?? self::DEFAULT_DB_HOST,
                'port' => (int)($config['DB_PORT'] ?? self::DEFAULT_DB_PORT),
                'dbname' => $dbName,
                'user' => $config['DB_USER'] ?? self::DEFAULT_DB_USER,
                'password' => $config['DB_PASSWORD'] ?? self::DEFAULT_DB_PASSWORD,
            ];
        }
        
        // Fallback to defaults
        return [
            'host' => self::DEFAULT_DB_HOST,
            'port' => self::DEFAULT_DB_PORT,
            'dbname' => self::DEFAULT_DB_NAME,
            'user' => self::DEFAULT_DB_USER,
            'password' => self::DEFAULT_DB_PASSWORD,
        ];
    }

    /**
     * Get database connection parameters
     */
    private static function getDbConfig(): array
    {
        static $config = null;
        if ($config === null) {
            $config = self::loadConfig();
        }
        return $config;
    }

    /**
     * Get database host
     */
    private static function getDbHost(): string
    {
        return self::getDbConfig()['host'];
    }

    /**
     * Get database port
     */
    private static function getDbPort(): int
    {
        return self::getDbConfig()['port'];
    }

    /**
     * Get database name
     */
    private static function getDbName(): string
    {
        return self::getDbConfig()['dbname'];
    }

    /**
     * Get database user
     */
    private static function getDbUser(): string
    {
        return self::getDbConfig()['user'];
    }

    /**
     * Get database password
     */
    private static function getDbPassword(): string
    {
        return self::getDbConfig()['password'];
    }

    /**
     * Check if PostgreSQL tests are enabled
     * PostgreSQL is enabled by default, can be disabled with TEST_WITH_SQLITE=1
     */
    public static function isEnabled(): bool
    {
        // PostgreSQL is default, disable only if explicitly requested
        return getenv('TEST_WITH_SQLITE') !== '1' && getenv('TEST_WITH_SQLITE') !== 'true';
    }

    /** Cache flag for the current process to avoid re-checking a healthy container */
    private static bool $alreadyHealthy = false;

    /**
     * Start PostgreSQL container if not running
     * 
     * Strategy:
     * 1. Check if our container is already running - if yes, use it
     * 2. Check if our container exists but stopped - if yes, start it
     * 3. If port is in use but not our container - try to connect anyway (might be our container from previous run)
     * 4. Only create new container if nothing exists
     */
    public static function ensureContainerRunning(): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        // Fast path: if already healthy in this process, skip all checks
        if (self::$alreadyHealthy) {
            return true;
        }

        // Try a quick connection first (already-running container)
        if (self::waitForHealthy(1)) {
            self::$alreadyHealthy = true;
            return true;
        }

        // Step 1: Check if our container is already running
        $output = [];
        $returnCode = 0;
        exec("docker ps --filter name=" . self::CONTAINER_NAME . " --format '{{.Names}}' 2>&1", $output, $returnCode);
        $isRunning = !empty($output) && in_array(self::CONTAINER_NAME, $output);

        if ($isRunning) {
            // Container is running, wait for it to be healthy
            $ok = self::waitForHealthy();
            if ($ok) {
                self::$alreadyHealthy = true;
            }
            return $ok;
        }

        // Step 2: Check if container exists but is stopped
        exec("docker ps -a --filter name=" . self::CONTAINER_NAME . " --format '{{.Names}}' 2>&1", $output, $returnCode);
        $containerExists = !empty($output) && in_array(self::CONTAINER_NAME, $output);

        if ($containerExists) {
            // Container exists but stopped, start it
            $composeFile = self::COMPOSE_FILE;
            if (file_exists($composeFile)) {
                exec("docker compose -f {$composeFile} start 2>&1", $output, $returnCode);
                if ($returnCode === 0) {
                    $ok = self::waitForHealthy();
                    if ($ok) {
                        self::$alreadyHealthy = true;
                    }
                    return $ok;
                }
            }
        }

        // Step 3: Try to connect to port (might be our container from previous run or another instance)
        if (self::waitForHealthy(2)) {
            // Successfully connected - port is in use but we can connect
            return true;
        }

        // Step 4: Create new container
        $composeFile = self::COMPOSE_FILE;
        if (!file_exists($composeFile)) {
            fwrite(STDERR, "Warning: docker-compose.test.yml not found. Skipping PostgreSQL tests.\n");
            return false;
        }

        $command = "docker compose -f {$composeFile} up -d 2>&1";
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $errorMsg = implode("\n", $output);
            
            // If port is in use, try one more time to connect (might be our container)
            if (strpos($errorMsg, 'address already in use') !== false) {
                fwrite(STDERR, "Note: Port " . self::getDbPort() . " is in use. Attempting to connect...\n");
                if (self::waitForHealthy(5)) {
                    return true; // Successfully connected to existing instance
                }
            }
            
            fwrite(STDERR, "Warning: Failed to start PostgreSQL container: {$errorMsg}\n");
            fwrite(STDERR, "Falling back to SQLite. Tests will use in-memory SQLite database.\n");
            return false;
        }

        $ok = self::waitForHealthy();
        if ($ok) {
            self::$alreadyHealthy = true;
        }
        return $ok;
    }

    /**
     * Wait for PostgreSQL to be ready
     */
    private static function waitForHealthy(int $maxAttempts = 30): bool
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                $pdo = new \PDO(
                    sprintf('pgsql:host=%s;port=%s;dbname=%s', self::getDbHost(), self::getDbPort(), self::getDbName()),
                    self::getDbUser(),
                    self::getDbPassword(),
                    [\PDO::ATTR_TIMEOUT => 2]
                );
                $pdo = null; // Close connection
                return true;
            } catch (\PDOException $e) {
                usleep(100000); // Wait 100ms
            }
        }
        return false;
    }

    /**
     * Create PDO connection to PostgreSQL test database
     */
    public static function createConnection(): ?\PDO
    {
        if (!self::ensureContainerRunning()) {
            return null;
        }

        try {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', self::getDbHost(), self::getDbPort(), self::getDbName());
            $pdo = new \PDO($dsn, self::getDbUser(), self::getDbPassword(), [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            // Create test database if it doesn't exist (should already exist from docker-compose)
            return $pdo;
        } catch (\PDOException $e) {
            fwrite(STDERR, "Warning: Failed to connect to PostgreSQL: " . $e->getMessage() . "\n");
            return null;
        }
    }

    /**
     * Get connection parameters
     */
    public static function getConnectionParams(): array
    {
        return self::getDbConfig();
    }

    /**
     * Stop PostgreSQL container (optional cleanup)
     */
    public static function stopContainer(): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $composeFile = self::COMPOSE_FILE;
        if (file_exists($composeFile)) {
            exec("docker compose -f {$composeFile} down 2>&1", $output, $returnCode);
        }
    }
}

