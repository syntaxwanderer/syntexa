<?php

declare(strict_types=1);

namespace Syntexa\Core\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Syntexa\UserFrontend\Domain\Entity\User;
use Syntexa\UserFrontend\Domain\Repository\UserRepositoryInterface;

class UserCreateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('user:create')
            ->setDescription('Create a new user')
            ->addArgument('email', InputArgument::OPTIONAL, 'User email')
            ->addArgument('password', InputArgument::OPTIONAL, 'User password')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'User name', '')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force creation even if user exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $io->title('Create User');

        // Get email
        $email = $input->getArgument('email');
        if (!$email) {
            $question = new Question('Enter email: ');
            $question->setValidator(function ($answer) {
                if (empty($answer) || !filter_var($answer, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Please enter a valid email address');
                }
                return $answer;
            });
            $email = $helper->ask($input, $output, $question);
        }

        // Get password
        $password = $input->getArgument('password');
        if (!$password) {
            $question = new Question('Enter password: ');
            $question->setHidden(true);
            $question->setValidator(function ($answer) {
                if (empty($answer) || strlen($answer) < 6) {
                    throw new \RuntimeException('Password must be at least 6 characters long');
                }
                return $answer;
            });
            $password = $helper->ask($input, $output, $question);
        }

        // Get name
        $name = $input->getOption('name');
        if (empty($name)) {
            $question = new Question('Enter name (optional): ', '');
            $name = $helper->ask($input, $output, $question);
        }

        // Get repository
        $container = \Syntexa\Core\Container\ContainerFactory::get();
        $userRepository = $container->get(\Syntexa\UserFrontend\Domain\Repository\UserRepositoryInterface::class);

        // Check if user exists
        if ($userRepository->exists($email)) {
            if (!$input->getOption('force')) {
                $io->error("User with email '{$email}' already exists. Use --force to overwrite.");
                return Command::FAILURE;
            }

            $question = new ConfirmationQuestion(
                "User '{$email}' already exists. Overwrite? [y/N]: ",
                false
            );
            if (!$helper->ask($input, $output, $question)) {
                $io->info('Cancelled.');
                return Command::SUCCESS;
            }
        }

        // Create user
        try {
            $user = new User(
                id: uniqid('user_', true),
                email: $email,
                passwordHash: User::hashPassword($password),
                name: $name,
                createdAt: new \DateTimeImmutable()
            );

            $userRepository->save($user);

            $io->success("User '{$email}' created successfully!");
            $io->table(
                ['Field', 'Value'],
                [
                    ['ID', $user->id],
                    ['Email', $user->email],
                    ['Name', $user->name ?: '(empty)'],
                    ['Created', $user->createdAt->format('Y-m-d H:i:s')],
                ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Failed to create user: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

