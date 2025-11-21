#!/usr/bin/env php
<?php

declare(strict_types=1);

use Syntexa\Core\Console\Application;

require_once __DIR__ . '/../vendor/autoload.php';

$application = new Application();
$application->run();

