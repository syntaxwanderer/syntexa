<?php

declare(strict_types=1);

namespace Syntexa\Core;

use Syntexa\Core\Discovery\AttributeDiscovery;
use Syntexa\Core\Queue\HandlerExecution;
use Syntexa\Core\Queue\QueueDispatcher;
use DI\Container;
use Syntexa\Core\Container\RequestScopedContainer;

/**
 * Minimal Syntexa Application
 */
class Application
{
    private Environment $environment;
    private Container $container;
    private RequestScopedContainer $requestScopedContainer;
    
    public function __construct(?Container $container = null)
    {
        $this->container = $container ?? \Syntexa\Core\Container\ContainerFactory::get();
        $this->requestScopedContainer = \Syntexa\Core\Container\ContainerFactory::getRequestScoped();
        $this->environment = $this->container->get(Environment::class);
    }
    
    public function getContainer(): Container
    {
        return $this->container;
    }
    
    public function getRequestScopedContainer(): RequestScopedContainer
    {
        return $this->requestScopedContainer;
    }
    
    public function getEnvironment(): Environment
    {
        return $this->environment;
    }
    
    public function handleRequest(Request $request): Response
    {
        // Clear superglobals for security (prevent accidental use of unvalidated data)
        \Syntexa\Core\Http\SecurityHelper::clearSuperglobals();
        
        // Initialize attribute discovery
        AttributeDiscovery::initialize();
        
        // Try to find route using AttributeDiscovery
        $route = AttributeDiscovery::findRoute($request->getPath(), $request->getMethod());
        
        if (!$route) {
            echo "⚠️  No route found for: {$request->getPath()} ({$request->getMethod()})\n";
        } else {
            echo "✅ Found route: {$route['path']} -> {$route['class']}\n";
        }
        
        if ($route) {
            return $this->handleRoute($route, $request);
        }
        
        // Fallback to simple routing
        $path = $request->getPath();
        if ($path === '/' || $path === '') {
            return $this->helloWorld($request);
        }

        return $this->notFound($request);
    }
    
    private function handleRoute(array $route, Request $request): Response
    {
        try {
            // Request/Handler flow
            if (($route['type'] ?? null) === 'http-request') {
                echo "🔄 Processing request route\n";
                $requestClass = $route['class'];
                $responseClass = $route['responseClass'] ?? null;
                $handlerClasses = $route['handlers'] ?? [];

                echo "📦 Request class: {$requestClass}\n";
                echo "📦 Response class: " . ($responseClass ?? 'null') . "\n";
                echo "📦 Handlers: " . count($handlerClasses) . "\n";

                // Instantiate DTOs
                $reqDto = class_exists($requestClass) ? new $requestClass() : null;
                if (!$reqDto) {
                    throw new \RuntimeException("Cannot instantiate request class: {$requestClass}");
                }
                
                // Hydrate Request DTO from HTTP Request data
                try {
                    $reqDto = \Syntexa\Core\Http\RequestDtoHydrator::hydrate($reqDto, $request);
                    // Allow DTO to access HTTP Request if it has setHttpRequest method
                    if (method_exists($reqDto, 'setHttpRequest')) {
                        $reqDto->setHttpRequest($request);
                    }
                    echo "✅ Hydrated Request DTO: {$requestClass}\n";
                } catch (\Throwable $e) {
                    echo "⚠️  Error hydrating Request DTO: " . $e->getMessage() . "\n";
                    // Continue with empty DTO if hydration fails
                }
                
                $resDto = ($responseClass && class_exists($responseClass)) ? new $responseClass() : null;

                // Fallback generic response if none supplied
                if ($resDto === null) {
                    echo "⚠️  Using fallback GenericResponse\n";
                    $resDto = new \Syntexa\Core\Http\Response\GenericResponse();
                }

                // Apply AsResponse defaults if present
                if ($resDto) {
                    $resolvedResponse = \Syntexa\Core\Discovery\AttributeDiscovery::getResolvedResponseAttributes(get_class($resDto));
                    if ($resolvedResponse) {
                        if (isset($resolvedResponse['handle']) && $resolvedResponse['handle'] && method_exists($resDto, 'setRenderHandle')) {
                            $resDto->setRenderHandle($resolvedResponse['handle']);
                        }
                        if (isset($resolvedResponse['context']) && method_exists($resDto, 'setRenderContext')) {
                            $resDto->setRenderContext($resolvedResponse['context']);
                        }
                        if (array_key_exists('format', $resolvedResponse) && method_exists($resDto, 'setRenderFormat')) {
                            $resDto->setRenderFormat($resolvedResponse['format']);
                        }
                        if (isset($resolvedResponse['renderer']) && method_exists($resDto, 'setRendererClass')) {
                            $resDto->setRendererClass($resolvedResponse['renderer']);
                        }
                    }
                    // Fallback: try to read attribute directly (for cases where getResolvedResponseAttributes returns null)
                    if (!method_exists($resDto, 'getRenderHandle') || !$resDto->getRenderHandle()) {
                        try {
                            $r = new \ReflectionClass($resDto);
                            $attrs = $r->getAttributes('Syntexa\\Core\\Attributes\\AsResponse');
                            if (!empty($attrs)) {
                                $a = $attrs[0]->newInstance();
                                if (method_exists($resDto, 'setRenderHandle') && $a->handle) {
                                    $resDto->setRenderHandle($a->handle);
                                }
                                if (method_exists($resDto, 'setRenderContext') && isset($a->context)) {
                                    $resDto->setRenderContext($a->context);
                                }
                                if (method_exists($resDto, 'setRenderFormat') && $a->format) {
                                    $resDto->setRenderFormat($a->format);
                                }
                                if (method_exists($resDto, 'setRendererClass') && $a->renderer) {
                                    $resDto->setRendererClass($a->renderer);
                                }
                            }
                            // If still no handle, try parent class
                            if (!method_exists($resDto, 'getRenderHandle') || !$resDto->getRenderHandle()) {
                                $parent = $r->getParentClass();
                                if ($parent) {
                                    $parentAttrs = $parent->getAttributes('Syntexa\\Core\\Attributes\\AsResponse');
                                    if (!empty($parentAttrs)) {
                                        $parentAttr = $parentAttrs[0]->newInstance();
                                        if (method_exists($resDto, 'setRenderHandle') && $parentAttr->handle) {
                                            $resDto->setRenderHandle($parentAttr->handle);
                                        }
                                        if (method_exists($resDto, 'setRenderFormat') && $parentAttr->format) {
                                            $resDto->setRenderFormat($parentAttr->format);
                                        }
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }
                }

                // Execute handlers in order
                foreach ($handlerClasses as $handlerMeta) {
                    $handlerClass = is_array($handlerMeta) ? ($handlerMeta['class'] ?? null) : $handlerMeta;
                    if (!$handlerClass) {
                        continue;
                    }

                    $execution = $handlerMeta['execution'] ?? HandlerExecution::Sync->value;
                    if ($execution === HandlerExecution::Async->value) {
                        QueueDispatcher::enqueue(
                            is_array($handlerMeta) ? $handlerMeta : ['class' => $handlerClass, 'for' => $requestClass],
                            $reqDto,
                            $resDto
                        );
                        continue;
                    }

                    echo "🔄 Executing handler: {$handlerClass}\n";
                    if (!class_exists($handlerClass)) {
                        echo "⚠️  Handler class not found: {$handlerClass}\n";
                        continue;
                    }
                    
                    // Use request-scoped container to resolve handler dependencies
                    // This ensures handlers get fresh instances for each request
                    try {
                        $handler = $this->requestScopedContainer->get($handlerClass);
                        
                        // Verify that properties are injected (especially important in Swoole)
                        $reflection = new \ReflectionClass($handler);
                        foreach ($reflection->getProperties() as $property) {
                            $attributes = $property->getAttributes(\DI\Attribute\Inject::class);
                            if (!empty($attributes)) {
                                $property->setAccessible(true);
                                $value = $property->getValue($handler);
                                if ($value === null) {
                                    throw new \RuntimeException(
                                        "Property {$property->getName()} in {$handlerClass} was not injected. " .
                                        "This usually means injectOn() failed or make() didn't inject properties."
                                    );
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // Don't fallback to direct instantiation - it won't work with property injection
                        // Instead, throw the error so we can see what's wrong
                        echo "❌ DI resolution failed for {$handlerClass}: {$e->getMessage()}\n";
                        echo "Stack trace: " . $e->getTraceAsString() . "\n";
                        throw new \RuntimeException("Failed to resolve handler {$handlerClass}: " . $e->getMessage(), 0, $e);
                    }
                    
                    if (method_exists($handler, 'handle')) {
                        $resDto = $handler->handle($reqDto, $resDto);
                        echo "✅ Handler executed: {$handlerClass}\n";
                    }
                }

                // Centralized rendering step (if requested by handlers)
                if (method_exists($resDto, 'getRenderHandle')) {
                    $handle = $resDto->getRenderHandle();
                    if ($handle) {
                        $context = method_exists($resDto, 'getRenderContext') ? $resDto->getRenderContext() : [];
                        $format = method_exists($resDto, 'getRenderFormat') ? $resDto->getRenderFormat() : null;
                        if ($format === null) {
                            // default to layout when handle provided
                            $format = \Syntexa\Core\Http\Response\ResponseFormat::Layout;
                        }
                        $rendererClass = method_exists($resDto, 'getRendererClass') ? $resDto->getRendererClass() : null;

                        if ($format === \Syntexa\Core\Http\Response\ResponseFormat::Json) {
                            $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            if (method_exists($resDto, 'setContent')) {
                                $resDto->setContent($json ?: '');
                            }
                            if (method_exists($resDto, 'setHeader')) {
                                $resDto->setHeader('Content-Type', 'application/json');
                            }
                        } elseif ($format === \Syntexa\Core\Http\Response\ResponseFormat::Layout) {
                            // Use provided renderer or default LayoutRenderer
                            $renderer = $rendererClass ?: 'Syntexa\\Frontend\\Layout\\LayoutRenderer';
                            if (class_exists($renderer) && method_exists($renderer, 'renderHandle')) {
                                $html = $renderer::renderHandle($handle, $context);
                                if (method_exists($resDto, 'setContent')) {
                                    $resDto->setContent($html);
                                }
                                if (method_exists($resDto, 'setHeader')) {
                                    $resDto->setHeader('Content-Type', 'text/html; charset=utf-8');
                                }
                            }
                        } else {
                            // raw/no-op
                        }
                    }
                }

                // Adapt to core Response
                // If handler returned a Core Response directly, use it
                if ($resDto instanceof \Syntexa\Core\Response) {
                    echo "✅ Handler returned Core Response directly\n";
                    return $resDto;
                }
                
                // If response DTO has toCoreResponse method, use it
                if (method_exists($resDto, 'toCoreResponse')) {
                    echo "✅ Converting to Core Response\n";
                    return $resDto->toCoreResponse();
                }
                
                // Generic fallback
                echo "⚠️  Using generic JSON fallback\n";
                return Response::json(['ok' => true]);
            }

            // Legacy controller flow
            $controller = new $route['class']();
            $method = $route['method'];
            $response = $method === '__invoke' ? $controller() : $controller->$method();
            return $response;
        } catch (\Throwable $e) {
            echo "❌ Error in handleRoute: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return \Syntexa\Core\Http\ErrorRenderer::render($e, $request);
        }
    }
    
    private function helloWorld(Request $request): Response
    {
        return Response::json([
            'message' => 'Hello World from Syntexa!',
            'framework' => $this->environment->get('APP_NAME', 'Syntexa'),
            'mode' => $this->detectRuntimeMode($request),
            'environment' => $this->environment->get('APP_ENV', 'prod'),
            'debug' => $this->environment->isDebug(),
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
            'swoole_server' => $request->getServer('SWOOLE_SERVER', 'not-set'),
            'server_software' => $request->getServer('SERVER_SOFTWARE', 'not-set'),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function detectRuntimeMode(Request $request): string
    {
            return 'swoole';
    }
    
    private function notFound(Request $request): Response
    {
        return Response::notFound('The requested resource was not found');
    }
}
