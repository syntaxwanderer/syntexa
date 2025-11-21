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

class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('Syntexa', '1.0.0');
        
        $this->addCommands([
            new ServerStartCommand(),
            new ServerStopCommand(),
            new ServerRestartCommand(),
            new RequestGenerateCommand(),
            new ResponseGenerateCommand(),
            new LayoutGenerateCommand(),
            new QueueWorkCommand(),
        ]);
    }
}

