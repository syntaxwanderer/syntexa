<?php

declare(strict_types=1);

namespace Syntexa\Core\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class QueueWorkCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('queue:work')
            ->setDescription('Run async handler worker')
            ->addArgument('transport', InputArgument::OPTIONAL, 'Queue transport (rabbitmq, in-memory)', null)
            ->addArgument('queue', InputArgument::OPTIONAL, 'Queue name', null)
            ->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Worker timeout in seconds', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $transport = $input->getArgument('transport');
        $queue = $input->getArgument('queue');

        $io->title('Queue Worker');
        
        try {
            $worker = new \Syntexa\Core\Queue\QueueWorker();
            $worker->run($transport, $queue);
        } catch (\Throwable $e) {
            $io->error('Worker failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

