<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Application\Handler\Request;

use DI\Attribute\Inject;
use Syntexa\Core\Attributes\AsRequestHandler;
use Syntexa\Core\Handler\HttpHandlerInterface;
use Syntexa\Core\Contract\RequestInterface;
use Syntexa\Core\Contract\ResponseInterface;
use Syntexa\Core\Response;
use Syntexa\UserFrontend\Application\Input\LoginFormRequest;
use Syntexa\UserFrontend\Application\Output\LoginFormResponse;
use Syntexa\UserDomain\Domain\Service\AuthService;
use Syntexa\UserDomain\Domain\Service\LoginAnalyticsService;

#[AsRequestHandler(for: LoginFormRequest::class)]
class LoginFormHandler implements HttpHandlerInterface
{
    /**
     * Property injection via PHP-DI attributes
     * This is the recommended approach for handlers/controllers according to PHP-DI best practices
     * 
     * @see https://php-di.org/doc/best-practices.html
     */
    #[Inject]
    private LoginAnalyticsService $analyticsService;

    #[Inject]
    private AuthService $authService;

    /**
     * @param LoginFormRequest $request
     * @param LoginFormResponse $response
     * @return LoginFormResponse
     */
    public function handle(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        /** @var LoginFormRequest $request */
        /** @var LoginFormResponse $response */

        // Log page visit
        $ip = $request->getServerParam('REMOTE_ADDR', 'unknown');
        $userAgent = $request->getServerParam('HTTP_USER_AGENT', 'unknown');
        $this->analyticsService->logPageVisit($ip, $userAgent);

        // Handle POST login
        if ($request->isPost()) {
            $email = $request->getEmail();
            $password = $request->getPassword();

            if (empty($email) || empty($password)) {
                $response->setContext([
                    'error' => 'Email and password are required',
                    'email' => $email,
                ]);
                return $response;
            }

            $user = $this->authService->authenticate($email, $password);
            if ($user === null) {
                $response->setContext([
                    'error' => 'Invalid email or password',
                    'email' => $email,
                ]);
                return $response;
            }

            // Login successful
            $this->authService->login($user);
            
            // Redirect to dashboard
            return Response::redirect('/dashboard');
        }

        // GET request - show login form
        // If already authenticated, redirect to dashboard
        if ($this->authService->isAuthenticated()) {
            return Response::redirect('/dashboard');
        }

        return $response;
    }
}

