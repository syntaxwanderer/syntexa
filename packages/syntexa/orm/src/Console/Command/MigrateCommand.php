<?php

declare(strict_types=1);

namespace Syntexa\Orm\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Syntexa\Orm\Connection\ConnectionPool;
use Syntexa\Orm\Migration\AbstractMigration;
use Syntexa\Orm\Migration\MigrationExecutor;
use Syntexa\Orm\Migration\MigrationFinder;
use Syntexa\Core\Environment;
use PDO;

class MigrateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('migrate')
            ->setDescription('Run database migrations')
            ->addOption('class', 'c', InputOption::VALUE_REQUIRED, 'Specific migration class to run')
            ->addOption('rollback', 'r', InputOption::VALUE_OPTIONAL, 'Rollback migration (optionally specify version)', false)
            ->addOption('status', 's', InputOption::VALUE_NONE, 'Show migration status')
            ->addOption('init', null, InputOption::VALUE_NONE, 'Initialize connection pool before running migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            // Get database connection
            $connection = $this->getConnection();
            $executor = new MigrationExecutor($connection);
            
            // Show status
            if ($input->getOption('status')) {
                return $this->showStatus($io, $executor);
            }
            
            // Rollback
            $rollback = $input->getOption('rollback');
            if ($rollback !== false) {
                return $this->rollbackMigration($io, $executor, $connection, $rollback);
            }
            
            // Run specific migration
            $class = $input->getOption('class');
            if ($class) {
                return $this->runMigration($io, $executor, $connection, $class);
            }
            
            // Run all pending migrations
            return $this->runAllMigrations($io, $executor, $connection);
            
        } catch (\Throwable $e) {
            $io->error('Migration failed: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->error($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function getConnection(): PDO
    {
        $projectRoot = $this->getProjectRoot();
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
        
        return new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function showStatus(SymfonyStyle $io, MigrationExecutor $executor): int
    {
        $io->title('Migration Status');
        
        $executed = $executor->getExecutedMigrations();
        $projectRoot = $this->getProjectRoot();
        $allMigrations = MigrationFinder::findMigrations($projectRoot);
        
        $rows = [];
        foreach ($allMigrations as $className) {
            $version = MigrationExecutor::getVersionFromClassName($className);
            $isExecuted = in_array($version, $executed, true);
            $rows[] = [
                $version,
                $isExecuted ? '✓' : '✗',
                $isExecuted ? 'Executed' : 'Pending',
            ];
        }
        
        if (empty($rows)) {
            $io->warning('No migrations found');
            return Command::SUCCESS;
        }
        
        $io->table(['Version', 'Status', 'State'], $rows);
        return Command::SUCCESS;
    }

    private function runMigration(
        SymfonyStyle $io,
        MigrationExecutor $executor,
        PDO $connection,
        string $className
    ): int {
        if (!class_exists($className)) {
            $io->error("Migration class not found: {$className}");
            return Command::FAILURE;
        }
        
        if (!is_subclass_of($className, AbstractMigration::class)) {
            $io->error("Class {$className} is not a migration (must extend AbstractMigration)");
            return Command::FAILURE;
        }
        
        $version = MigrationExecutor::getVersionFromClassName($className);
        $migration = new $className($connection);
        
        $io->info("Running migration: {$version}");
        if ($migration->getDescription()) {
            $io->comment($migration->getDescription());
        }
        
        $executor->execute($migration, $version);
        $io->success("Migration {$version} completed successfully");
        
        return Command::SUCCESS;
    }

    private function runAllMigrations(
        SymfonyStyle $io,
        MigrationExecutor $executor,
        PDO $connection
    ): int {
        $projectRoot = $this->getProjectRoot();
        $migrations = MigrationFinder::findMigrations($projectRoot);
        $executed = $executor->getExecutedMigrations();
        
        if (empty($migrations)) {
            $io->warning('No migrations found');
            return Command::SUCCESS;
        }
        
        $pending = array_filter($migrations, function ($className) use ($executor, $executed) {
            $version = MigrationExecutor::getVersionFromClassName($className);
            return !in_array($version, $executed, true);
        });
        
        if (empty($pending)) {
            $io->success('All migrations are already executed');
            return Command::SUCCESS;
        }
        
        $io->info('Found ' . count($pending) . ' pending migration(s)');
        
        foreach ($pending as $className) {
            $version = MigrationExecutor::getVersionFromClassName($className);
            $migration = new $className($connection);
            
            $io->section("Running migration: {$version}");
            if ($migration->getDescription()) {
                $io->comment($migration->getDescription());
            }
            
            $executor->execute($migration, $version);
            $io->success("Migration {$version} completed");
        }
        
        $io->success('All migrations completed successfully');
        return Command::SUCCESS;
    }

    private function rollbackMigration(
        SymfonyStyle $io,
        MigrationExecutor $executor,
        PDO $connection,
        ?string $version
    ): int {
        $projectRoot = $this->getProjectRoot();
        $migrations = MigrationFinder::findMigrations($projectRoot);
        $executed = $executor->getExecutedMigrations();
        
        if (empty($executed)) {
            $io->warning('No executed migrations to rollback');
            return Command::SUCCESS;
        }
        
        // If version specified, rollback that one
        if ($version !== null) {
            $className = null;
            foreach ($migrations as $migrationClass) {
                if (MigrationExecutor::getVersionFromClassName($migrationClass) === $version) {
                    $className = $migrationClass;
                    break;
                }
            }
            
            if ($className === null) {
                $io->error("Migration version not found: {$version}");
                return Command::FAILURE;
            }
            
            $migration = new $className($connection);
            $io->info("Rolling back migration: {$version}");
            $executor->rollback($migration, $version);
            $io->success("Migration {$version} rolled back successfully");
            return Command::SUCCESS;
        }
        
        // Rollback last executed migration
        $lastVersion = end($executed);
        $className = null;
        foreach ($migrations as $migrationClass) {
            if (MigrationExecutor::getVersionFromClassName($migrationClass) === $lastVersion) {
                $className = $migrationClass;
                break;
            }
        }
        
        if ($className === null) {
            $io->error("Migration class not found for version: {$lastVersion}");
            return Command::FAILURE;
        }
        
        $migration = new $className($connection);
        $io->info("Rolling back last migration: {$lastVersion}");
        $executor->rollback($migration, $lastVersion);
        $io->success("Migration {$lastVersion} rolled back successfully");
        
        return Command::SUCCESS;
    }

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
        return dirname(__DIR__, 6);
    }
}

