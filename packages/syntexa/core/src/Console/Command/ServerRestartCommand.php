<?php

declare(strict_types=1);

namespace Syntexa\Core\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ServerRestartCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('server:restart')
            ->setDescription('Restart Swoole HTTP server')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Port to restart', '9501');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $port = $input->getOption('port');

        $stopCommand = new ServerStopCommand();
        $stopCommand->setApplication($this->getApplication());
        $stopResult = $stopCommand->run($input, $output);

        if ($stopResult !== Command::SUCCESS) {
            return $stopResult;
        }

        sleep(1);

        $startCommand = new ServerStartCommand();
        $startCommand->setApplication($this->getApplication());
        return $startCommand->run($input, $output);
    }
}

