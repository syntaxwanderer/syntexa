<?php

declare(strict_types=1);

namespace Syntexa\Orm\Migration;

use PDO;
use Syntexa\Core\Environment;

/**
 * Simple migration runner
 * Creates database tables based on SQL files
 */
class MigrationRunner
{
    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        if ($connection !== null) {
            $this->connection = $connection;
        } else {
            // Create direct PDO connection for migrations (not using pool)
            // Load .env from project root
            $projectRoot = self::getProjectRoot();
            $envFile = $projectRoot . '/.env';
            $env = [];
            
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '#') === 0) {
                        continue;
                    }
                    if (strpos($line, '=') !== false) {
                        [$key, $value] = explode('=', $line, 2);
                        $env[trim($key)] = trim($value);
                    }
                }
            }
            
            $dbConfig = [
                'host' => $env['DB_HOST'] ?? 'localhost',
                'port' => (int) ($env['DB_PORT'] ?? '5432'),
                'dbname' => $env['DB_NAME'] ?? 'syntexa',
                'user' => $env['DB_USER'] ?? 'postgres',
                'password' => $env['DB_PASSWORD'] ?? '',
            ];
            
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['dbname']
            );
            
            $password = $dbConfig['password'];
            if (empty($password)) {
                throw new \RuntimeException('DB_PASSWORD is not set in .env file');
            }
            
            $this->connection = new PDO($dsn, $dbConfig['user'], $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
    }
    
    private static function getProjectRoot(): string
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
        return dirname(__DIR__, 6);
    }

    /**
     * Run migration from SQL file
     */
    public function run(string $sqlFile): void
    {
        if (!file_exists($sqlFile)) {
            throw new \RuntimeException("Migration file not found: {$sqlFile}");
        }

        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new \RuntimeException("Failed to read migration file: {$sqlFile}");
        }

        // Split by semicolons and execute each statement
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($stmt) => !empty($stmt) && !str_starts_with($stmt, '--')
        );

        $this->connection->beginTransaction();
        try {
            foreach ($statements as $statement) {
                $this->connection->exec($statement);
            }
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw new \RuntimeException("Migration failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if table exists
     */
    public function tableExists(string $tableName): bool
    {
        $stmt = $this->connection->prepare("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = :table
            )
        ");
        $stmt->execute(['table' => $tableName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (bool) ($result['exists'] ?? false);
    }
}

