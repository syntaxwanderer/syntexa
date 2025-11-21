<?php

declare(strict_types=1);

namespace Syntexa\Core\Container;

use Syntexa\Core\Contract\RequestInterface;
use Syntexa\Core\Contract\ResponseInterface;

/**
 * Example handler demonstrating DI usage
 * This shows how to inject services into handlers
 */
class ExampleHandler
{
    public function __construct(
        private ExampleService $exampleService
    ) {}

    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Use injected service
        $message = $this->exampleService->doSomething();
        echo "📝 Example handler: {$message}\n";
        
        return $response;
    }
}

