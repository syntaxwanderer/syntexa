<?php

declare(strict_types=1);

namespace Syntexa\Core\Container;

/**
 * Example service to demonstrate DI usage
 * This is just an example - remove or modify as needed
 */
class ExampleService
{
    public function doSomething(): string
    {
        return "Service executed at " . date('Y-m-d H:i:s');
    }
}

