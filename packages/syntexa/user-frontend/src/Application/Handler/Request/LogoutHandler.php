<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Application\Handler\Request;

use DI\Attribute\Inject;
use Syntexa\Core\Attributes\AsRequestHandler;
use Syntexa\Core\Handler\HttpHandlerInterface;
use Syntexa\Core\Contract\RequestInterface;
use Syntexa\Core\Contract\ResponseInterface;
use Syntexa\Core\Response;
use Syntexa\UserFrontend\Application\Input\Http\LogoutRequest;
use Syntexa\UserFrontend\Domain\Service\AuthService;

#[AsRequestHandler(for: LogoutRequest::class)]
class LogoutHandler implements HttpHandlerInterface
{
    #[Inject]
    private AuthService $authService;

    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var LogoutRequest $request */
        
        $this->authService->logout();
        
        return Response::redirect('/login');
    }
}

