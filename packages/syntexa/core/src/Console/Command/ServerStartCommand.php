<?php

declare(strict_types=1);

namespace Syntexa\Core\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ServerStartCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('server:start')
            ->setDescription('Start Swoole HTTP server');

        $this->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Port to listen on', '9501');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $port = $input->getOption('port');
        
        if (!extension_loaded('swoole')) {
            $io->error('Swoole extension is required but not installed.');
            $io->note('Install Swoole: https://www.swoole.co.uk/docs/get-started/installation');
            return Command::FAILURE;
        }

        $rootDir = $this->getProjectRoot();
        $pidFile = $rootDir . '/var/swoole.pid';
        $serverFile = $rootDir . '/server.php';
        
        if (!file_exists($serverFile)) {
            $io->error("Server file not found: {$serverFile}");
            return Command::FAILURE;
        }
        
        // Check if server is already running
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && function_exists('posix_kill') && posix_kill((int)$pid, 0)) {
                $io->warning("Server is already running (PID: {$pid}). Stopping it first...");
                $this->stopServer($io, $port);
            }
        }

        $io->title('Starting Syntexa Framework');
        $io->success('Swoole extension found');
        $io->text("Starting in Swoole mode on port {$port}...");
        $io->note("Server will be available at: http://localhost:{$port}");

        // Change to project root directory
        chdir($rootDir);
        
        // Set environment variable
        putenv("SWOOLE_PORT={$port}");
        
        // Require server file - this will block until server stops
        require $serverFile;

        // This line should never be reached (server->start() blocks)
        return Command::SUCCESS;
    }

    private function stopServer(SymfonyStyle $io, string $port): void
    {
        $rootDir = $this->getProjectRoot();
        $pidFile = $rootDir . '/var/swoole.pid';
        
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && function_exists('posix_kill')) {
                posix_kill((int)$pid, SIGTERM);
                sleep(1);
                if (posix_kill((int)$pid, 0)) {
                    posix_kill((int)$pid, SIGKILL);
                }
            } else {
                // Fallback to shell command
                exec("kill -TERM {$pid} 2>/dev/null || kill -9 {$pid} 2>/dev/null");
            }
            @unlink($pidFile);
        }

        // Kill processes on port
        $pids = $this->getPidsOnPort($port);
        foreach ($pids as $pid) {
            if (function_exists('posix_kill')) {
                posix_kill($pid, SIGTERM);
            } else {
                exec("kill -TERM {$pid} 2>/dev/null");
            }
        }
        sleep(1);
        
        $pids = $this->getPidsOnPort($port);
        foreach ($pids as $pid) {
            if (function_exists('posix_kill')) {
                posix_kill($pid, SIGKILL);
            } else {
                exec("kill -9 {$pid} 2>/dev/null");
            }
        }
    }

    private function getPidsOnPort(string $port): array
    {
        $pids = [];
        $output = shell_exec("ss -ltnp 2>/dev/null | awk -v port=\":{$port}\" '\$4 ~ port {print \$6}' | sed -n 's/.*pid=\\([0-9]*\\).*/\\1/p' | sort -u");
        if ($output) {
            foreach (explode("\n", trim($output)) as $pid) {
                if ($pid) {
                    $pids[] = (int)$pid;
                }
            }
        }
        return $pids;
    }
}

