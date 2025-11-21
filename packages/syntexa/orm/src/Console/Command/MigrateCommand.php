<?php

declare(strict_types=1);

namespace Syntexa\Orm\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Syntexa\Orm\Connection\ConnectionPool;
use Syntexa\Orm\Migration\MigrationRunner;
use Syntexa\Core\Environment;

class MigrateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('migrate')
            ->setDescription('Run database migrations')
            ->addOption('file', 'f', \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, 'Specific migration file to run')
            ->addOption('init', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Initialize connection pool before running migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            // Initialize connection pool if needed
            if ($input->getOption('init')) {
                $dbConfig = [
                    'host' => Environment::getEnvValue('DB_HOST', 'localhost'),
                    'port' => (int) Environment::getEnvValue('DB_PORT', '5432'),
                    'dbname' => Environment::getEnvValue('DB_NAME', 'syntexa'),
                    'user' => Environment::getEnvValue('DB_USER', 'postgres'),
                    'password' => Environment::getEnvValue('DB_PASSWORD', ''),
                    'charset' => Environment::getEnvValue('DB_CHARSET', 'utf8'),
                    'pool_size' => (int) Environment::getEnvValue('DB_POOL_SIZE', '10'),
                ];
                ConnectionPool::initialize($dbConfig);
            }

            $runner = new MigrationRunner();
            
            $file = $input->getOption('file');
            if ($file) {
                // Run specific migration file
                if (!file_exists($file)) {
                    $io->error("Migration file not found: {$file}");
                    return Command::FAILURE;
                }
                $io->info("Running migration: {$file}");
                $runner->run($file);
                $io->success("Migration completed successfully");
            } else {
                // Run all migrations from packages/*/migrations/
                $projectRoot = $this->getProjectRoot();
                $migrationDirs = glob($projectRoot . '/packages/*/migrations/*.sql');
                
                if (empty($migrationDirs)) {
                    $io->warning('No migration files found');
                    return Command::SUCCESS;
                }
                
                foreach ($migrationDirs as $migrationFile) {
                    $io->info("Running migration: " . basename($migrationFile));
                    $runner->run($migrationFile);
                }
                
                $io->success('All migrations completed successfully');
            }
        } catch (\Throwable $e) {
            $io->error('Migration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

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

