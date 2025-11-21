<?php

declare(strict_types=1);

namespace Syntexa\Core\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LayoutGenerateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('layout:generate')
            ->setDescription('Copy module layouts into src/')
            ->addArgument('layout', InputArgument::OPTIONAL, 'Specific layout handle (optional)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Generate all layouts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $layout = $input->getArgument('layout');
        $all = $input->getOption('all');

        $rootDir = $this->getProjectRoot();
        require $rootDir . '/tools/layout-generator.php';

        return Command::SUCCESS;
    }
}

