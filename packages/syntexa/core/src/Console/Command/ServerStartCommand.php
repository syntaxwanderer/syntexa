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
    protected function configure(): void
    {
        $this->setName('server:start')
            ->setDescription('Start Syntexa Environment (Docker Compose)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = $this->getProjectRoot();
        
        $io->title('Starting Syntexa Environment (Docker)');

        if (!file_exists($projectRoot . '/docker-compose.yml')) {
            $io->error('docker-compose.yml not found. Cannot start environment.');
            return Command::FAILURE;
        }

        $io->section('Starting containers...');
        
        $process = new Process(['docker', 'compose', 'up', '-d'], $projectRoot);
        $process->setTimeout(null);
        
        $process->run(function ($type, $buffer) use ($io) {
            $io->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $io->error('Failed to start environment.');
            return Command::FAILURE;
        }

        $io->success('Syntexa environment started successfully!');
        
        $io->note([
            'Blockchain Server: http://localhost:8080',
            'Node 1 (Shop 1):   http://localhost:8081',
            'Node 2 (Shop 2):   http://localhost:8082',
        ]);
        
        $io->text('To view logs: docker compose logs -f');
        $io->text('To stop: bin/syntexa server:stop (or docker compose down)');

        return Command::SUCCESS;
    }
}
