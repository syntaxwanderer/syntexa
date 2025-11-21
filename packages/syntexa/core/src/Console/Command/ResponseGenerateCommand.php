<?php

declare(strict_types=1);

namespace Syntexa\Core\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ResponseGenerateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('response:generate')
            ->setDescription('Build/refresh response wrapper(s)')
            ->addArgument('response', InputArgument::OPTIONAL, 'Specific response class name (optional)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Generate all responses');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $response = $input->getArgument('response');
        $all = $input->getOption('all');

        try {
            if ($all || $response === null) {
                \Syntexa\Core\CodeGen\ResponseWrapperGenerator::generateAll();
                $io->success('Generated all response wrappers');
            } else {
                \Syntexa\Core\CodeGen\ResponseWrapperGenerator::generate($response);
                $io->success("Generated response wrapper for: {$response}");
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

