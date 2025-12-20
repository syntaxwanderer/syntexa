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
use Syntexa\Orm\CodeGen\DomainWrapperGenerator;

class EntityGenerateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('entity:generate')
            ->setDescription('Build/refresh entity wrapper(s) - automatically generates both storage and domain wrappers')
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
                
                // Also generate domain wrappers for entities that have domainClass
                try {
                    DomainWrapperGenerator::generateAll();
                } catch (\Throwable $e) {
                    // Domain generation is optional, just show warning
                    $io->warning('Domain wrapper generation had issues: ' . $e->getMessage());
                }
            } else {
                EntityWrapperGenerator::generate($entity);
                $io->success("Generated entity wrapper for: {$entity}");
                
                // Try to generate domain wrapper if entity has domainClass
                try {
                    $this->generateDomainWrapperForEntity($entity);
                } catch (\Throwable $e) {
                    // Domain generation is optional, just show info
                    $io->info("Note: Domain wrapper not generated: " . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Generate domain wrapper for entity if it has domainClass configured
     */
    private function generateDomainWrapperForEntity(string $entityIdentifier): void
    {
        // Find entity by identifier
        $entities = EntityWrapperGenerator::bootstrapDefinitions();
        $target = EntityWrapperGenerator::resolveTarget($entities, $entityIdentifier);
        
        if ($target === null) {
            throw new \RuntimeException("Entity '{$entityIdentifier}' not found");
        }
        
        /** @var \Syntexa\Orm\Attributes\AsEntity $attr */
        $attr = $target['attr'];
        if ($attr->domainClass === null) {
            throw new \RuntimeException("Entity '{$entityIdentifier}' has no domainClass configured");
        }
        
        // Generate domain wrapper using domain class short name
        $domainReflection = new \ReflectionClass($attr->domainClass);
        $domainShortName = $domainReflection->getShortName();
        
        DomainWrapperGenerator::generate($domainShortName);
    }
}

