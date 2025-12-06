<?php

declare(strict_types=1);

namespace Syntexa\Orm\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Syntexa\Orm\CodeGen\InfrastructureEntityGenerator;

/**
 * Build infrastructure database representations that merge
 * entity owners and module-provided traits into one place.
 */
class DatabaseBuildCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('database:build')
            ->setDescription('Generate infrastructure entity files under src/infrastructure/database')
            ->addArgument('entity', InputArgument::OPTIONAL, 'Specific entity class (FQN or short name)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Generate all entities (default)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entity = $input->getArgument('entity');
        $all = $input->getOption('all');

        try {
            if ($entity !== null) {
                InfrastructureEntityGenerator::generate($entity);
                $io->success("Infrastructure entity generated for {$entity}");
            } else {
                $count = InfrastructureEntityGenerator::generateAll();
                $io->success("Generated {$count} infrastructure entity file(s)");
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}


