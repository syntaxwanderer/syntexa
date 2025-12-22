<?php

declare(strict_types=1);

namespace Syntexa\Core\Console;

use Symfony\Component\Console\Application as SymfonyApplication;
use Syntexa\Core\Console\Command\ServerStartCommand;
use Syntexa\Core\Console\Command\ServerStopCommand;
use Syntexa\Core\Console\Command\ServerRestartCommand;
use Syntexa\Core\Console\Command\RequestGenerateCommand;
use Syntexa\Core\Console\Command\ResponseGenerateCommand;
use Syntexa\Core\Console\Command\LayoutGenerateCommand;
use Syntexa\Core\Console\Command\QueueWorkCommand;
use Syntexa\Core\Console\Command\UserCreateCommand;
use Syntexa\Core\Console\Command\TestHandlerCommand;

class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('Syntexa', '1.0.0');
        
        $commands = [
            new ServerStartCommand(),
            new ServerStopCommand(),
            new ServerRestartCommand(),
            new RequestGenerateCommand(),
            new ResponseGenerateCommand(),
            new LayoutGenerateCommand(),
            new QueueWorkCommand(),
            new UserCreateCommand(),
            new TestHandlerCommand(),
        ];

        // Add ORM commands if available
        if (class_exists(\Syntexa\Orm\Console\Command\EntityGenerateCommand::class)) {
            $commands[] = new \Syntexa\Orm\Console\Command\EntityGenerateCommand();
        }
        if (class_exists(\Syntexa\Orm\Console\Command\DomainGenerateCommand::class)) {
            $commands[] = new \Syntexa\Orm\Console\Command\DomainGenerateCommand();
        }
        if (class_exists(\Syntexa\Orm\Console\Command\MigrateCommand::class)) {
            $commands[] = new \Syntexa\Orm\Console\Command\MigrateCommand();
        }
        if (class_exists(\Syntexa\Orm\Console\Command\DatabaseBuildCommand::class)) {
            $commands[] = new \Syntexa\Orm\Console\Command\DatabaseBuildCommand();
        }
        if (class_exists(\Syntexa\Orm\Console\Command\BlockchainConsumeCommand::class)) {
            $commands[] = new \Syntexa\Orm\Console\Command\BlockchainConsumeCommand();
        }

        $this->addCommands($commands);
    }
}

