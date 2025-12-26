<?php

declare(strict_types=1);

namespace Syntexa\Core\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command;

use Symfony\Component\Process\Process;

class ServerRestartCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('server:restart')
            ->setDescription('Restart Syntexa Environment (Docker)')
            ->addOption('service', 's', InputOption::VALUE_OPTIONAL, 'Specific service to restart');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = $this->getProjectRoot();
        $service = $input->getOption('service');

        $io->title('Restarting Syntexa Environment (Docker)');

        if (!file_exists($projectRoot . '/docker-compose.yml')) {
            $io->error('docker-compose.yml not found.');
            return Command::FAILURE;
        }

        $command = ['docker', 'compose', 'restart'];
        if ($service) {
            $command[] = $service;
            $io->section("Restarting service: $service");
        } else {
            $io->section('Restarting all containers...');
        }
        
        $process = new Process($command, $projectRoot);
        $process->setTimeout(null);
        
        $process->run(function ($type, $buffer) use ($io) {
             $io->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $io->error('Failed to restart environment.');
            return Command::FAILURE;
        }

        $io->success('Syntexa environment restarted successfully!');
        return Command::SUCCESS;
    }
}

