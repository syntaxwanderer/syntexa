<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Application\Handler\Request;

use DI\Attribute\Inject;
use Syntexa\Core\Attributes\AsRequestHandler;
use Syntexa\Core\Handler\HttpHandlerInterface;
use Syntexa\Core\Contract\RequestInterface;
use Syntexa\Core\Contract\ResponseInterface;
use Syntexa\Core\Response;
use Syntexa\UserFrontend\Application\Input\DashboardRequest;
use Syntexa\UserFrontend\Application\Output\DashboardResponse;
use Syntexa\UserDomain\Domain\Service\AuthService;

#[AsRequestHandler(for: DashboardRequest::class)]
class DashboardHandler implements HttpHandlerInterface
{
    #[Inject]
    private AuthService $authService;

    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var DashboardRequest $request */
        /** @var DashboardResponse $response */

        // Require authentication
        try {
            $user = $this->authService->requireAuth();
        } catch (\RuntimeException $e) {
            // Redirect to login if not authenticated
            return Response::redirect('/login');
        }

        // Set user data in response context
        $response->setContext([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
            ],
            'title' => 'Dashboard',
        ]);

        return $response;
    }
}

