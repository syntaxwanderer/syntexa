<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Orm;

/**
 * Test Infrastructure Bootstrap
 * 
 * Automatically starts Docker containers required for tests:
 * - PostgreSQL (for app tests)
 * - RabbitMQ (for blockchain tests)
 * - Multiple PostgreSQL databases (for multi-node blockchain tests)
 * 
 * This ensures that when you run `bin/phpunit`, all required infrastructure
 * is automatically started and ready.
 */
class TestInfrastructureBootstrap
{
    private const COMPOSE_FILE = __DIR__ . '/../../../docker-compose.test.yml';
    private const MAX_WAIT_TIME = 60; // seconds

    /**
     * Ensure all test infrastructure is running
     */
    public static function ensureInfrastructure(): void
    {
        // Skip if explicitly disabled
        if (getenv('SKIP_DOCKER_SETUP') === '1' || getenv('SKIP_DOCKER_SETUP') === 'true') {
            return;
        }

        // Skip if docker-compose file doesn't exist
        if (!file_exists(self::COMPOSE_FILE)) {
            fwrite(STDERR, "Warning: docker-compose.test.yml not found. Skipping Docker setup.\n");
            return;
        }

        // Check if docker compose is available
        if (!self::isDockerComposeAvailable()) {
            fwrite(STDERR, "Warning: docker compose not available. Skipping Docker setup.\n");
            return;
        }

        // Step 1: Clean up old test containers
        self::cleanupOldContainers();

        // Step 2: Start all services
        self::startServices();

        // Step 3: Wait for services to be ready
        self::waitForServices();

        // Step 4: Run migrations
        self::runMigrations();
    }

    /**
     * Clean up old test containers (with test- prefix)
     */
    private static function cleanupOldContainers(): void
    {
        // Stop and remove all containers with test- prefix
        $output = [];
        $returnCode = 0;
        exec('docker ps -a --filter "name=test-" --format "{{.Names}}" 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0 || empty($output)) {
            return; // No containers found or error
        }

        $containers = array_filter(array_map('trim', $output));
        if (empty($containers)) {
            return;
        }

        // Stop containers
        foreach ($containers as $container) {
            exec(sprintf('docker stop %s 2>&1', escapeshellarg($container)), $stopOutput, $stopReturnCode);
        }

        // Remove containers
        foreach ($containers as $container) {
            exec(sprintf('docker rm %s 2>&1', escapeshellarg($container)), $rmOutput, $rmReturnCode);
        }
    }

    /**
     * Check if docker compose is available
     */
    private static function isDockerComposeAvailable(): bool
    {
        $output = [];
        $returnCode = 0;
        exec('docker compose version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Start Docker Compose services
     */
    private static function startServices(): void
    {
        $composeFile = self::COMPOSE_FILE;
        
        // Check which containers are already running
        $runningContainers = self::getRunningServices();
        
        // Check if required containers are running (by container name, not service name)
        $requiredContainers = ['test-postgres-test', 'test-rabbitmq-test', 'test-postgres-node1-blockchain', 'test-postgres-node2-blockchain'];
        $allRunning = true;
        foreach ($requiredContainers as $container) {
            if (!in_array($container, $runningContainers)) {
                $allRunning = false;
                break;
            }
        }

        if ($allRunning) {
            // All containers are running, just verify they're healthy
            return;
        }

        // Start services
        $command = sprintf('docker compose -f %s up -d 2>&1', escapeshellarg($composeFile));
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $errorMsg = implode("\n", $output);
            
            // If port is in use, that's okay - services might already be running
            if (strpos($errorMsg, 'address already in use') !== false) {
                fwrite(STDERR, "Note: Some ports are in use. Services might already be running.\n");
                return;
            }
            
            fwrite(STDERR, "Warning: Failed to start Docker services: {$errorMsg}\n");
            fwrite(STDERR, "Tests may fail if they require Docker infrastructure.\n");
        }
    }

    /**
     * Get list of running container names
     */
    private static function getRunningServices(): array
    {
        $output = [];
        $returnCode = 0;
        exec('docker ps --format "{{.Names}}" 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            return [];
        }

        return array_filter(array_map('trim', $output));
    }

    /**
     * Wait for services to be ready
     */
    private static function waitForServices(): void
    {
        $startTime = time();
        
        // Wait for PostgreSQL (app tests)
        self::waitForPostgreSQL('test-postgres-test', 'localhost', 5436, 30);
        
        // Wait for RabbitMQ (blockchain tests)
        self::waitForRabbitMQ('test-rabbitmq-test', 'localhost', 5673, 30);
        
        // Wait for PostgreSQL node1 (multi-node tests)
        self::waitForPostgreSQL('test-postgres-node1-blockchain', 'localhost', 5434, 20);
        
        // Wait for PostgreSQL node2 (multi-node tests)
        self::waitForPostgreSQL('test-postgres-node2-blockchain', 'localhost', 5435, 20);
        
        $elapsed = time() - $startTime;
        if ($elapsed > 5) {
            fwrite(STDERR, sprintf("Infrastructure ready in %d seconds.\n", $elapsed));
        }
    }

    /**
     * Wait for PostgreSQL to be ready and ensure database exists
     */
    private static function waitForPostgreSQL(string $containerName, string $host, int $port, int $maxAttempts): bool
    {
        // Check if container is running
        $running = self::isContainerRunning($containerName);
        if (!$running) {
            // Container not running, skip check (might not be needed for this test)
            return false;
        }

        // Try to connect and ensure database exists
        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                // Connect to postgres database first
                // Use credentials from .env or defaults
                $user = $_ENV['DB_USER'] ?? 'test';
                $password = $_ENV['DB_PASSWORD'] ?? 'test';
                $pdo = new \PDO(
                    sprintf('pgsql:host=%s;port=%d;dbname=postgres', $host, $port),
                    $user,
                    $password,
                    [\PDO::ATTR_TIMEOUT => 2]
                );
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $pdo->query('SELECT 1');
                
                // Database is ready
                return true;
            } catch (\PDOException $e) {
                // Not ready yet, wait
                usleep(500000); // 0.5 seconds
            }
        }

        fwrite(STDERR, sprintf("Warning: PostgreSQL container %s not ready after %d attempts.\n", $containerName, $maxAttempts));
        return false;
    }

    /**
     * Wait for RabbitMQ to be ready
     */
    private static function waitForRabbitMQ(string $containerName, string $host, int $port, int $maxAttempts): bool
    {
        // Check if container is running
        $running = self::isContainerRunning($containerName);
        if (!$running) {
            // Container not running, skip check (might not be needed for this test)
            return false;
        }

        // Try to connect via socket
        for ($i = 0; $i < $maxAttempts; $i++) {
            $socket = @fsockopen($host, $port, $errno, $errstr, 2);
            if ($socket !== false) {
                fclose($socket);
                return true; // Connected successfully
            }
            usleep(500000); // 0.5 seconds
        }

        fwrite(STDERR, sprintf("Warning: RabbitMQ container %s not ready after %d attempts.\n", $containerName, $maxAttempts));
        return false;
    }

    /**
     * Check if container is running
     */
    private static function isContainerRunning(string $containerName): bool
    {
        $output = [];
        $returnCode = 0;
        exec(sprintf('docker ps --filter name=%s --format "{{.Names}}" 2>&1', escapeshellarg($containerName)), $output, $returnCode);
        
        if ($returnCode !== 0) {
            return false;
        }

        return !empty($output) && in_array($containerName, array_map('trim', $output));
    }

    /**
     * Run database migrations
     */
    /**
     * Run database migrations
     */
    private static function runMigrations(): void
    {
        $projectRoot = dirname(self::COMPOSE_FILE);
        $migrateScript = $projectRoot . '/bin/syntexa';
        
        // Check if migrations directory exists
        $migrationsDir = $projectRoot . '/src/infrastructure/migrations';
        if (!is_dir($migrationsDir)) {
            return; // No migrations to run
        }

        // Check if migrate script exists
        if (!file_exists($migrateScript)) {
            fwrite(STDERR, "Warning: bin/syntexa not found. Skipping migrations.\n");
            return;
        }

        // Ensure DB_NAME ends with _test for migrations
        $dbName = $_ENV['DB_NAME'] ?? 'syntexa';
        if (!str_ends_with($dbName, '_test')) {
            $dbName .= '_test';
        }
        
        // Set environment variables for migrations
        $env = [
            'DB_HOST' => $_ENV['DB_HOST'] ?? 'localhost',
            'DB_PORT' => $_ENV['DB_PORT'] ?? '5436',
            'DB_NAME' => $dbName,
            'DB_USER' => $_ENV['DB_USER'] ?? 'test',
            'DB_PASSWORD' => $_ENV['DB_PASSWORD'] ?? 'test',
        ];
        
        $envString = '';
        foreach ($env as $key => $value) {
            $envString .= sprintf('%s=%s ', $key, escapeshellarg($value));
        }

        // Run migrations with test database
        $command = sprintf(
            'cd %s && %sphp %s migrate 2>&1',
            escapeshellarg($projectRoot),
            $envString,
            escapeshellarg($migrateScript)
        );
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            $errorMsg = implode("\n", $output);
            // Don't show error if migrations are already executed
            if (strpos($errorMsg, 'already executed') === false && strpos($errorMsg, 'No migrations found') === false) {
                fwrite(STDERR, "Warning: Failed to run migrations: {$errorMsg}\n");
                fwrite(STDERR, "Tests may fail if they require database schema.\n");
            }
        } else {
            // Only show output if there were migrations run
            $outputStr = implode("\n", $output);
            if (strpos($outputStr, 'pending') !== false || strpos($outputStr, 'Running migration') !== false || strpos($outputStr, 'migration') !== false) {
                fwrite(STDERR, $outputStr . "\n");
            }
        }
    }
}

