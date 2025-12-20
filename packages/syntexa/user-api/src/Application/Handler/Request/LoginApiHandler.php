<?php

declare(strict_types=1);

namespace Syntexa\User\Application\Handler\Request;

use Syntexa\Core\Attributes\AsRequestHandler;
use Syntexa\Core\Contract\RequestInterface;
use Syntexa\Core\Contract\ResponseInterface;
use Syntexa\Core\Handler\HttpHandlerInterface;
use Syntexa\User\Application\Input\LoginApiRequest;
use Syntexa\User\Application\Output\Http\LoginApiResponse;

#[AsRequestHandler(for: LoginApiRequest::class)]
class LoginApiHandler implements HttpHandlerInterface
{
    /**
     * @param LoginApiRequest $request
     * @param LoginApiResponse $response
     * @return LoginApiResponse
     */
    public function handle(RequestInterface $request, ResponseInterface $response): LoginApiResponse
    {
        // demo payload
        if (method_exists($response, 'setRenderContext')) {
            $response->setRenderContext([
                'ok' => true,
                'message' => 'Login API reached'
            ]);
        }
        return $response;
    }
}

