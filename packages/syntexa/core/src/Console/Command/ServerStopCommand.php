<?php

declare(strict_types=1);

namespace Syntexa\Core\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ServerStopCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('server:stop')
            ->setDescription('Stop Swoole HTTP server')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Port to stop', '9501');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $port = $input->getOption('port');
        
        $io->title("Stopping Syntexa on port {$port}...");

        $rootDir = $this->getProjectRoot();
        $pidFile = $rootDir . '/var/swoole.pid';
        
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid) {
                if (function_exists('posix_kill') && posix_kill((int)$pid, 0)) {
                    $io->text("Master PID: {$pid}");
                    posix_kill((int)$pid, SIGTERM);
                    sleep(1);
                    if (posix_kill((int)$pid, 0)) {
                        posix_kill((int)$pid, SIGKILL);
                    }
                } else {
                    // Fallback to shell command
                    exec("kill -TERM {$pid} 2>/dev/null || kill -9 {$pid} 2>/dev/null");
                }
            }
            @unlink($pidFile);
        }

        // Kill all processes on port
        $attempts = 0;
        while ($attempts < 5) {
            $pids = $this->getPidsOnPort($port);
            if (empty($pids)) {
                if ($attempts > 0) {
                    $io->success("All processes on port {$port} terminated.");
                } else {
                    $io->info("No processes found on port {$port}.");
                }
                break;
            }

            $io->text("PIDs on port {$port}: " . implode(', ', $pids));
            foreach ($pids as $pid) {
                if (function_exists('posix_kill')) {
                    posix_kill($pid, SIGTERM);
                } else {
                    exec("kill -TERM {$pid} 2>/dev/null");
                }
            }
            sleep(1);

            $pids = $this->getPidsOnPort($port);
            if (empty($pids)) {
                break;
            }

            foreach ($pids as $pid) {
                if (function_exists('posix_kill')) {
                    posix_kill($pid, SIGKILL);
                } else {
                    exec("kill -9 {$pid} 2>/dev/null");
                }
            }
            sleep(1);
            $attempts++;
        }

        if ($attempts >= 5) {
            $io->warning("Unable to terminate all processes after {$attempts} attempts.");
            return Command::FAILURE;
        }

        $io->success("Stopped.");
        return Command::SUCCESS;
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

