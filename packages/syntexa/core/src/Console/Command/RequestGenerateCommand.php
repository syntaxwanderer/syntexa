<?php

declare(strict_types=1);

namespace Syntexa\Core\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RequestGenerateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('request:generate')
            ->setDescription('Build/refresh request wrapper(s)')
            ->addArgument('request', InputArgument::OPTIONAL, 'Specific request class name (optional)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Generate all requests');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $request = $input->getArgument('request');
        $all = $input->getOption('all');

        $rootDir = $this->getProjectRoot();
        require $rootDir . '/tools/request-generator.php';

        return Command::SUCCESS;
    }
}

