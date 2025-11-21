<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Application\Handler\Request;

use DI\Attribute\Inject;
use Syntexa\Core\Attributes\AsRequestHandler;
use Syntexa\Core\Handler\HttpHandlerInterface;
use Syntexa\Core\Contract\RequestInterface;
use Syntexa\Core\Contract\ResponseInterface;
use Syntexa\UserFrontend\Application\Input\LoginFormRequest;
use Syntexa\UserFrontend\Application\Output\LoginFormResponse;
use Syntexa\UserFrontend\Domain\Service\LoginAnalyticsService;

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

    /**
     * @param LoginFormRequest $request
     * @param LoginFormResponse $response
     * @return LoginFormResponse
     */
    public function handle(RequestInterface $request, ResponseInterface $response): LoginFormResponse
    {
        /** @var LoginFormRequest $request */
        /** @var LoginFormResponse $response */

        // Use injected service to log the page visit
        $ip = $request->getServerParam('REMOTE_ADDR', 'unknown');
        $userAgent = $request->getServerParam('HTTP_USER_AGENT', 'unknown');
        
        $this->analyticsService->logPageVisit($ip, $userAgent);
        
        // You can also use the service to get statistics
        $visitCount = $this->analyticsService->getVisitCount();
        echo "📊 Total login page visits: {$visitCount}\n";

        // Defaults are defined in LoginFormResponse; handlers may override if needed
        return $response;
    }
}

