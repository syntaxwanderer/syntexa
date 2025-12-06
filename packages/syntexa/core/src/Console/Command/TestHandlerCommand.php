<?php

declare(strict_types=1);

namespace Syntexa\Core\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Syntexa\Core\Container\ContainerFactory;
use Syntexa\Core\Container\RequestScopedContainer;

class TestHandlerCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('test:handler')
            ->setDescription('Test handler instantiation and property injection')
            ->addArgument('handler', InputArgument::REQUIRED, 'Handler class name (FQN)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $handlerClass = $input->getArgument('handler');

        $io->title('Testing Handler: ' . $handlerClass);

        try {
            // Test 1: Direct container get()
            $io->section('Test 1: Direct container->get()');
            $container = ContainerFactory::get();
            try {
                $handler1 = $container->get($handlerClass);
                $io->success('✅ get() succeeded');
                $this->inspectHandler($io, $handler1, $handlerClass);
            } catch (\Throwable $e) {
                $io->error('❌ get() failed: ' . $e->getMessage());
            }

            // Test 2: RequestScopedContainer get()
            $io->section('Test 2: RequestScopedContainer->get()');
            $requestScoped = ContainerFactory::getRequestScoped();
            try {
                $handler2 = $requestScoped->get($handlerClass);
                $io->success('✅ RequestScopedContainer->get() succeeded');
                $this->inspectHandler($io, $handler2, $handlerClass);
            } catch (\Throwable $e) {
                $io->error('❌ RequestScopedContainer->get() failed: ' . $e->getMessage());
            }

            // Test 3: Direct make() + injectOn()
            $io->section('Test 3: make() + injectOn()');
            try {
                $handler3 = $container->make($handlerClass);
                $container->injectOn($handler3);
                $io->success('✅ make() + injectOn() succeeded');
                $this->inspectHandler($io, $handler3, $handlerClass);
            } catch (\Throwable $e) {
                $io->error('❌ make() + injectOn() failed: ' . $e->getMessage());
            }

            // Test 4: Check if handler has definition
            $io->section('Test 4: Container definitions');
            $hasDefinition = $container->has($handlerClass);
            $io->text('Has definition: ' . ($hasDefinition ? '✅ yes' : '❌ no'));
            
            if ($hasDefinition) {
                try {
                    $definition = $container->getDefinition($handlerClass);
                    $io->text('Definition type: ' . get_class($definition));
                } catch (\Throwable $e) {
                    $io->text('Cannot get definition: ' . $e->getMessage());
                }
            }

        } catch (\Throwable $e) {
            $io->error('Fatal error: ' . $e->getMessage());
            $io->text($e->getTraceAsString());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function inspectHandler(SymfonyStyle $io, object $handler, string $handlerClass): void
    {
        $reflection = new \ReflectionClass($handler);
        
        $io->text('Class: ' . get_class($handler));
        $io->text('Properties:');
        
        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $name = $property->getName();
            $value = $property->getValue($handler);
            
            // Check for #[Inject] attribute
            $hasInject = !empty($property->getAttributes(\DI\Attribute\Inject::class));
            $injectMark = $hasInject ? ' [#[Inject]]' : '';
            
            if ($value === null) {
                $io->text("  ❌ {$name}: NULL (not initialized){$injectMark}");
            } else {
                $io->text("  ✅ {$name}: " . get_class($value) . $injectMark);
            }
        }
    }
}

