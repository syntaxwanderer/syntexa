<?php

declare(strict_types=1);

namespace Syntexa\Core\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Process\Process;

class ServerStartCommand extends BaseCommand
{
    private array $processes = [];
    private bool $running = true;

    protected function configure(): void
    {
        $this->setName('server:start')
            ->setDescription('Start Swoole HTTP server with queue workers and interactive statistics')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Port to listen on', '9501')
            ->addOption('workers', 'w', InputOption::VALUE_OPTIONAL, 'Number of queue workers', '2')
            ->addOption('no-stats', null, InputOption::VALUE_NONE, 'Disable interactive statistics');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $port = $input->getOption('port');
        $workers = (int)$input->getOption('workers');
        $showStats = !$input->getOption('no-stats');
        
        if (!extension_loaded('swoole')) {
            $io->error('Swoole extension is required but not installed.');
            $io->note('Install Swoole: https://www.swoole.co.uk/docs/get-started/installation');
            return Command::FAILURE;
        }

        $rootDir = $this->getProjectRoot();
        $pidFile = $rootDir . '/var/swoole.pid';
        $serverFile = $rootDir . '/server.php';
        $statsFile = $rootDir . '/var/server-stats.json';
        $queueStatsFile = $rootDir . '/var/queue-stats.json';
        
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

        // Initialize stats files
        @mkdir(dirname($statsFile), 0777, true);
        file_put_contents($statsFile, json_encode([
            'requests' => 0,
            'errors' => 0,
            'start_time' => time(),
            'uptime' => 0,
        ]));
        file_put_contents($queueStatsFile, json_encode([
            'processed' => 0,
            'failed' => 0,
            'start_time' => time(),
        ]));

        $io->title('Starting Syntexa Framework');
        $io->success('Swoole extension found');
        
        // Start server in background
        $io->text("Starting Swoole server on port {$port}...");
        $serverProcess = $this->startServer($rootDir, $serverFile, $port);
        $this->processes[] = $serverProcess;
        
        // Wait a bit for server to start
        sleep(2);
        
        // Start queue workers
        if ($workers > 0) {
            $io->text("Starting {$workers} queue worker(s)...");
            for ($i = 0; $i < $workers; $i++) {
                $workerProcess = $this->startQueueWorker($rootDir, $i);
                $this->processes[] = $workerProcess;
                usleep(500000); // 0.5s delay between workers
            }
        }

        $io->success("Server started at: http://localhost:{$port}");
        $io->note("Press Ctrl+C to stop all processes");

        // Handle signals for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'onSignal']);
            pcntl_signal(SIGTERM, [$this, 'onSignal']);
        }

        if ($showStats) {
            $this->showInteractiveStats($io, $output, $statsFile, $queueStatsFile);
        } else {
            // Just wait for processes
            $this->waitForProcesses();
        }

        return Command::SUCCESS;
    }

    private function startServer(string $rootDir, string $serverFile, string $port): Process
    {
        $process = new Process([
            PHP_BINARY,
            $serverFile
        ], $rootDir, [
            'SWOOLE_PORT' => $port,
        ]);
        
        $process->setTimeout(null);
        $process->start();
        
        return $process;
    }

    private function startQueueWorker(string $rootDir, int $workerId): Process
    {
        $process = new Process([
            PHP_BINARY,
            'bin/syntexa',
            'queue:work'
        ], $rootDir);
        
        $process->setTimeout(null);
        $process->start();
        
        return $process;
    }

    private function showInteractiveStats(SymfonyStyle $io, OutputInterface $output, string $statsFile, string $queueStatsFile): void
    {
        // Use sections for smooth updates without flickering
        $headerSection = $output->section();
        $tableSection = $output->section();
        $footerSection = $output->section();
        
        $headerSection->writeln('');
        $headerSection->writeln('<fg=cyan>Syntexa Framework - Server Statistics</>');
        $headerSection->writeln('');
        
        $footerSection->writeln('');
        $footerSection->writeln('<fg=yellow>Press Ctrl+C to stop</>');
        
        while ($this->running) {
            // Handle signals
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // Server Stats
            $serverStats = $this->readStats($statsFile);
            $queueStats = $this->readStats($queueStatsFile);
            $swooleStats = $this->getSwooleStats();
            
            $uptime = time() - ($serverStats['start_time'] ?? time());
            $uptimeFormatted = $this->formatUptime($uptime);
            $reqPerSec = $uptime > 0 ? round($serverStats['requests'] / $uptime, 2) : 0;

            // Build unified table
            $table = new Table($tableSection);
            $table->setHeaders(['Metric', 'Value']);
            
            $rows = [
                ['Status', '<info>Running</info>'],
                ['Uptime', $uptimeFormatted],
                ['HTTP Requests', number_format($serverStats['requests'] ?? 0)],
                ['Errors', '<fg=red>' . number_format($serverStats['errors'] ?? 0) . '</>'],
                ['Requests/sec', number_format($reqPerSec, 2)],
                ['Queue Processed', number_format($queueStats['processed'] ?? 0)],
                ['Queue Failed', '<fg=red>' . number_format($queueStats['failed'] ?? 0) . '</>'],
            ];
            
            // Add Swoole stats if available
            if ($swooleStats) {
                $rows[] = ['', '']; // Separator
                $rows[] = ['<fg=magenta>Swoole Statistics</>', ''];
                $rows[] = ['Active Connections', number_format($swooleStats['connection_num'] ?? 0)];
                $rows[] = ['Total Requests', number_format($swooleStats['request_count'] ?? 0)];
                $rows[] = ['Active Workers', number_format($swooleStats['worker_num'] ?? 0)];
                $rows[] = ['Idle Workers', number_format($swooleStats['idle_worker_num'] ?? 0)];
                $rows[] = ['Memory Usage', $this->formatBytes($swooleStats['memory_total'] ?? 0)];
                $rows[] = ['Memory Peak', $this->formatBytes($swooleStats['memory_peak'] ?? 0)];
                $rows[] = ['Coroutines', number_format($swooleStats['coroutine_num'] ?? 0)];
            }
            
            $table->setRows($rows);
            
            // Clear previous table and render new one
            $tableSection->clear();
            $table->render();

            // Check if processes are still running
            foreach ($this->processes as $process) {
                if (!$process->isRunning()) {
                    $io->warning('One of the processes has stopped');
                    $this->running = false;
                    break;
                }
            }

            if (!$this->running) {
                break;
            }

            // Wait 2 seconds before next update
            sleep(2);
        }

        $this->stopAllProcesses($io);
    }

    private function readStats(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }
        
        $content = file_get_contents($file);
        if (!$content) {
            return [];
        }
        
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private function getSwooleStats(): ?array
    {
        $pidFile = $this->getProjectRoot() . '/var/swoole.pid';
        if (!file_exists($pidFile)) {
            return null;
        }

        $pid = trim(file_get_contents($pidFile));
        if (!$pid) {
            return null;
        }

        // Try to get stats via Swoole's stats API
        // Note: This requires the server to expose stats via a file or API
        // For now, we'll try to read from a stats file if the server writes it
        $statsFile = $this->getProjectRoot() . '/var/swoole-stats.json';
        if (file_exists($statsFile)) {
            $content = file_get_contents($statsFile);
            if ($content) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }

        return null;
    }

    private function formatUptime(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        } else {
            return sprintf('%ds', $secs);
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function onSignal(int $signal): void
    {
        $this->running = false;
    }

    private function waitForProcesses(): void
    {
        while ($this->running) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            
            foreach ($this->processes as $process) {
                if (!$process->isRunning()) {
                    $this->running = false;
                    break;
                }
            }
            
            if (!$this->running) {
                break;
            }
            
            sleep(1);
        }
    }

    private function stopAllProcesses(SymfonyStyle $io): void
    {
        $io->text('');
        $io->text('Stopping all processes...');
        
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop(5, SIGTERM);
            }
        }
        
        // Also stop via PID file
        $rootDir = $this->getProjectRoot();
        $pidFile = $rootDir . '/var/swoole.pid';
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && function_exists('posix_kill')) {
                posix_kill((int)$pid, SIGTERM);
            }
        }
        
        $io->success('All processes stopped');
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
                exec("kill -TERM {$pid} 2>/dev/null || kill -9 {$pid} 2>/dev/null");
            }
            @unlink($pidFile);
        }

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
