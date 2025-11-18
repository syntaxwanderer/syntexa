<?php

declare(strict_types=1);

namespace Syntexa\Modules\UserApi\Handler\Request;

use Syntexa\Core\Attributes\AsRequestHandler;
use Syntexa\Core\Contract\RequestInterface;
use Syntexa\Core\Contract\ResponseInterface;
use Syntexa\Core\Handler\HttpHandlerInterface;
use Syntexa\User\Application\Input\Http\LoginApiRequest;
use Syntexa\User\Application\Output\Http\LoginApiResponse;

/**
 * Project-specific handler that extends module logic
 * This handler runs AFTER the module's LoginApiHandler
 */
#[AsRequestHandler(for: LoginApiRequest::class)]
class ProjectLoginApiHandler implements HttpHandlerInterface
{
    /**
     * @param LoginApiRequest $request
     * @param LoginApiResponse $response
     * @return LoginApiResponse
     */
    public function handle(RequestInterface $request, ResponseInterface $response): LoginApiResponse
    {
        /** @var LoginApiResponse $response */
        
        // Example: enrich response context with metadata (no override needed)
        if (method_exists($response, 'setRenderContext')) {
            $context = method_exists($response, 'getRenderContext') ? $response->getRenderContext() : [];
            $context['requestId'] = $request->id ?? null;
            $context['processedBy'] = self::class;
            $response->setRenderContext($context);
        }
        
        return $response;
    }
}

