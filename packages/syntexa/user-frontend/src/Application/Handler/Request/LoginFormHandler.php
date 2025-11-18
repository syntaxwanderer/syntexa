<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Application\Handler\Request;

use Syntexa\Core\Attributes\AsRequestHandler;
use Syntexa\Core\Handler\HttpHandlerInterface;
use Syntexa\Core\Contract\RequestInterface;
use Syntexa\Core\Contract\ResponseInterface;
use Syntexa\UserFrontend\Application\Input\LoginFormRequest;
use Syntexa\UserFrontend\Application\Output\LoginFormResponse;

#[AsRequestHandler(for: LoginFormRequest::class)]
class LoginFormHandler implements HttpHandlerInterface
{
    /**
     * @param LoginFormRequest $request
     * @param LoginFormResponse $response
     * @return LoginFormResponse
     */
    public function handle(RequestInterface $request, ResponseInterface $response): LoginFormResponse
    {
        /** @var LoginFormResponse $response */
        // Defaults are defined in LoginFormResponse; handlers may override if needed
        return $response;
    }
}

