<?php

declare(strict_types=1);

namespace Syntexa\Orm\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Syntexa\Orm\CodeGen\DomainWrapperGenerator;

class DomainGenerateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('domain:generate')
            ->setDescription('Build/refresh domain model wrapper(s) for cross-module extensions')
            ->addArgument('domain', InputArgument::OPTIONAL, 'Specific domain class name (optional)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Generate all domain wrappers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $domain = $input->getArgument('domain');
        $all = $input->getOption('all');

        try {
            if ($all || $domain === null) {
                DomainWrapperGenerator::generateAll();
                $io->success('Generated all domain wrappers');
            } else {
                DomainWrapperGenerator::generate($domain);
                $io->success("Generated domain wrapper for: {$domain}");
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

