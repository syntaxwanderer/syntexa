<?php

declare(strict_types=1);

namespace Syntexa\Orm\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Syntexa\Orm\CodeGen\EntityWrapperGenerator;

class EntityGenerateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('entity:generate')
            ->setDescription('Build/refresh entity wrapper(s)')
            ->addArgument('entity', InputArgument::OPTIONAL, 'Specific entity class name (optional)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Generate all entities');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entity = $input->getArgument('entity');
        $all = $input->getOption('all');

        try {
            if ($all || $entity === null) {
                EntityWrapperGenerator::generateAll();
                $io->success('Generated all entity wrappers');
            } else {
                EntityWrapperGenerator::generate($entity);
                $io->success("Generated entity wrapper for: {$entity}");
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

