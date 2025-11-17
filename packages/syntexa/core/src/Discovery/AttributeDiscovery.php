<?php

declare(strict_types=1);

namespace Syntexa\Core\Discovery;

use Syntexa\Core\Attributes\AsRequest;
use Syntexa\Core\Attributes\AsRequestHandler;
use Syntexa\Core\Attributes\AsResponseOverride;
use Syntexa\Core\Config\EnvValueResolver;
use Syntexa\Core\ModuleRegistry;
use Syntexa\Core\IntelligentAutoloader;
use ReflectionClass;

/**
 * Discovers and caches attributes from PHP classes
 * 
 * This class scans the src/ directory for classes with specific attributes
 * and builds a registry of controllers and routes.
 */
class AttributeDiscovery
{
    private static array $routes = [];
    private static array $httpRequests = [];
    private static array $httpHandlers = [];
    private static array $requestClassAliases = [];
    private static array $responseAttrOverrides = [];
    private static bool $initialized = false;
    
    /**
     * Initialize the discovery system
     * This should be called once at server startup
     */
    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }
        
        echo "🔍 Discovering attributes...\n";
        
        $startTime = microtime(true);
        
        // Initialize intelligent autoloader first
        IntelligentAutoloader::initialize();
        
        // Initialize module registry
        ModuleRegistry::initialize();
        
        // Scan attributes using intelligent autoloader
        self::scanAttributesIntelligently();
        
        $endTime = microtime(true);
        
        echo "✅ Found " . count(self::$routes) . " routes\n";
        echo "✅ Found " . count(self::$httpRequests) . " requests\n";
        echo "⏱️  Discovery took " . round(($endTime - $startTime) * 1000, 2) . "ms\n";
        
        self::$initialized = true;
    }
    
    /**
     * Get all discovered routes
     */
    public static function getRoutes(): array
    {
        return self::$routes;
    }
    
    /**
     * Find a route by path and method
     */
    public static function findRoute(string $path, string $method = 'GET'): ?array
    {
        echo "🔍 Looking for route: {$path} ({$method})\n";
        foreach (self::$routes as $route) {
            if ($route['path'] === $path && in_array($method, $route['methods'])) {
                echo "✅ Found matching route: {$route['path']}\n";
                // enrich with request handlers if applicable
                if (($route['type'] ?? null) === 'http-request') {
                    $reqClass = $route['class'];
                    echo "📦 Enriching request route with class: {$reqClass}\n";
                    $extra = self::$httpRequests[$reqClass] ?? null;
                    if ($extra) {
                        $route['handlers'] = $extra['handlers'];
                        $route['responseClass'] = $extra['responseClass'];
                        echo "✅ Enriched with " . count($extra['handlers']) . " handlers\n";
                    } else {
                        echo "⚠️  No extra data found for request class: {$reqClass}\n";
                    }
                }
                return $route;
            }
        }
        
        echo "⚠️  No route found for {$path} ({$method})\n";
        return null;
    }
    
    /**
     * Scan attributes using intelligent autoloader
     */
    private static function scanAttributesIntelligently(): void
    {
        self::$routes = [];
        self::$httpRequests = [];
        self::$httpHandlers = [];
        self::$requestClassAliases = [];

        // Find all classes with AsRequest attribute
        $httpRequestClasses = array_filter(
            IntelligentAutoloader::findClassesWithAttribute(AsRequest::class),
            fn ($class) => str_starts_with($class, 'Syntexa\\')
        );
        echo "🔍 Found " . count($httpRequestClasses) . " request classes\n";
        $requestCandidates = [];
        foreach ($httpRequestClasses as $className) {
            try {
                $class = new ReflectionClass($className);
                $attrs = $class->getAttributes(AsRequest::class);
                if (empty($attrs)) {
                    continue;
                }
                /** @var AsRequest $attr */
                $attr = $attrs[0]->newInstance();
                $key = self::buildRequestKey($attr, $class);
                $requestCandidates[$key][] = [
                    'class' => $class,
                    'className' => $className,
                    'attr' => $attr,
                    'file' => $class->getFileName() ?: '',
                    'priority' => self::determineSourcePriority($class->getFileName() ?: ''),
                    'isProject' => self::isProjectRequest($class->getFileName() ?: ''),
                ];
            } catch (\Throwable $e) {
                echo "⚠️  Error analyzing request {$className}: " . $e->getMessage() . "\n";
            }
        }

        foreach ($requestCandidates as $key => $candidates) {
            $projectCandidates = array_values(array_filter($candidates, fn ($c) => $c['isProject']));
            if (empty($projectCandidates)) {
                echo "⚠️  Skipping request '{$key}' – generate wrapper in src/ to activate it.\n";
                continue;
            }
            usort($projectCandidates, fn ($a, $b) => $b['priority'] <=> $a['priority']);
            $selected = $projectCandidates[0];

            foreach ($candidates as $candidate) {
                self::$requestClassAliases[$candidate['className']] = $selected['className'];
            }

            self::registerRequestCandidate($selected);
        }

        // Apply response overrides from src (AsResponseOverride) — only render hints, not class swap
        self::collectResponseOverrides();

        // Find handlers and map to requests
        $httpHandlerClasses = array_filter(
            IntelligentAutoloader::findClassesWithAttribute(AsRequestHandler::class),
            fn ($class) => str_starts_with($class, 'Syntexa\\')
        );
        echo "🔍 Found " . count($httpHandlerClasses) . " request handler classes\n";
        foreach ($httpHandlerClasses as $className) {
            try {
                $class = new ReflectionClass($className);
                $attrs = $class->getAttributes(AsRequestHandler::class);
                if (!empty($attrs)) {
                    /** @var AsRequestHandler $attr */
                    $attr = $attrs[0]->newInstance();
                    $for = $attr->getFor();
                    if (isset(self::$requestClassAliases[$for])) {
                        $for = self::$requestClassAliases[$for];
                    }
                    echo "🔗 Handler: {$class->getName()} -> for: {$for}\n";
                    self::$httpHandlers[$class->getName()] = [
                        'handlerClass' => $class->getName(),
                        'for' => $for,
                    ];
                    if (isset(self::$httpRequests[$for])) {
                        self::$httpRequests[$for]['handlers'][] = $class->getName();
                        echo "✅ Mapped handler {$class->getName()} to request {$for}\n";
                    } else {
                        echo "⚠️  Request class not found for handler: {$for}\n";
                    }
                }
            } catch (\Throwable $e) {
                echo "⚠️  Error analyzing request handler {$className}: " . $e->getMessage() . "\n";
            }
        }

        // Discover frontend block handlers (optional)
        if (class_exists('Syntexa\\Frontend\\Attributes\\AsBlockHandler') && class_exists('Syntexa\\Frontend\\Layout\\BlockHandlerRegistry')) {
            $asBlockHandler = 'Syntexa\\Frontend\\Attributes\\AsBlockHandler';
            $blockHandlerClasses = IntelligentAutoloader::findClassesWithAttribute($asBlockHandler);
            echo "🔍 Found " . count($blockHandlerClasses) . " block handler classes\n";
            foreach ($blockHandlerClasses as $className) {
                try {
                    $class = new \ReflectionClass($className);
                    $attrs = $class->getAttributes($asBlockHandler);
                    if (!empty($attrs)) {
                        $attr = $attrs[0]->newInstance();
                        $for = $attr->getFor();
                        $prio = $attr->getPriority();
                        \Syntexa\Frontend\Layout\BlockHandlerRegistry::register($for, $class->getName(), $prio);
                        echo "✅ Registered block handler {$class->getName()} for {$for} (priority {$prio})\n";
                    }
                } catch (\Throwable $e) {
                    echo "⚠️  Error analyzing block handler {$className}: " . $e->getMessage() . "\n";
                }
            }
        }

        // Discover layout overrides (optional)
        if (class_exists('Syntexa\\Frontend\\Attributes\\AsLayoutOverride') && class_exists('Syntexa\\Frontend\\Layout\\LayoutOverrideRegistry')) {
            $asLayoutOverride = 'Syntexa\\Frontend\\Attributes\\AsLayoutOverride';
            $overrideClasses = array_filter(
                IntelligentAutoloader::findClassesWithAttribute($asLayoutOverride),
                fn ($class) => str_starts_with($class, 'Syntexa\\')
            );
            echo "🔍 Found " . count($overrideClasses) . " layout override classes\n";
            foreach ($overrideClasses as $className) {
                try {
                    $class = new \ReflectionClass($className);
                    $file = $class->getFileName() ?: '';
                    // Only process overrides from project src/ (not packages)
                    $isProjectSrc = (strpos($file, '/src/') !== false) && (strpos($file, '/packages/syntexa/') === false);
                    if (!$isProjectSrc) {
                        continue;
                    }
                    $attrs = $class->getAttributes($asLayoutOverride);
                    if (!empty($attrs)) {
                        $attr = $attrs[0]->newInstance();
                        $handle = $attr->handle;
                        $operations = $attr->operations;
                        $priority = $attr->priority;
                        \Syntexa\Frontend\Layout\LayoutOverrideRegistry::register($handle, $operations, $priority);
                        echo "✅ Registered layout override for handle '{$handle}' with " . count($operations) . " operations (priority {$priority})\n";
                    }
                } catch (\Throwable $e) {
                    echo "⚠️  Error analyzing layout override {$className}: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    private static function registerRequestCandidate(array $candidate): void
    {
        /** @var ReflectionClass $class */
        $class = $candidate['class'];
        /** @var AsRequest $attr */
        $attr = $candidate['attr'];

        $path = EnvValueResolver::resolve($attr->path);
        $methods = EnvValueResolver::resolve($attr->methods);
        $name = $attr->name !== null ? EnvValueResolver::resolve($attr->name) : null;
        $responseWith = $attr->responseWith !== '' ? EnvValueResolver::resolve($attr->responseWith) : '';

        self::$httpRequests[$class->getName()] = [
            'requestClass' => $class->getName(),
            'path' => $path,
            'methods' => $methods,
            'name' => $name ?? $class->getShortName(),
            'responseClass' => $responseWith ?: null,
            'file' => $class->getFileName(),
            'handlers' => [],
        ];

        self::$routes[] = [
            'path' => $path,
            'methods' => $methods,
            'name' => $name ?? $class->getShortName(),
            'class' => $class->getName(),
            'method' => '__invoke',
            'requirements' => EnvValueResolver::resolve($attr->requirements),
            'defaults' => EnvValueResolver::resolve($attr->defaults),
            'options' => EnvValueResolver::resolve($attr->options),
            'tags' => EnvValueResolver::resolve($attr->tags),
            'public' => $attr->public,
            'type' => 'http-request'
        ];

        echo "✅ Registered request: {$path} -> {$class->getName()} (source: {$candidate['file']})\n";
    }

    private static function buildRequestKey(AsRequest $attr, ReflectionClass $class): string
    {
        $path = EnvValueResolver::resolve($attr->path);
        $methods = EnvValueResolver::resolve($attr->methods);
        $methodKey = implode(',', $methods);
        $name = $attr->name !== null ? EnvValueResolver::resolve($attr->name) : $class->getShortName();

        return $name . '|' . $path . '|' . $methodKey;
    }

    private static function determineSourcePriority(string $file): int
    {
        if ($file === '') {
            return 0;
        }

        if (str_contains($file, '/src/modules/')) {
            return 400;
        }

        if (self::isProjectRequest($file)) {
            return 300;
        }

        if (str_contains($file, '/packages/')) {
            return 200;
        }

        return 100;
    }

    private static function isProjectRequest(string $file): bool
    {
        if ($file === '') {
            return false;
        }

        $projectRoot = dirname(__DIR__, 5);
        $projectSrc = $projectRoot . '/src/';

        return str_starts_with($file, $projectSrc);
    }
    
    
    /**
     * Collect response overrides declared with AsResponseOverride.
     * Store class replacement and attribute overrides for later usage.
     */
    private static function collectResponseOverrides(): void
    {
        $overrideClasses = array_filter(
            IntelligentAutoloader::findClassesWithAttribute(AsResponseOverride::class),
            fn ($class) => str_starts_with($class, 'Syntexa\\')
        );
        if (empty($overrideClasses)) {
            return;
        }
        $overrides = [];
        foreach ($overrideClasses as $className) {
            try {
                $rc = new \ReflectionClass($className);
                $file = $rc->getFileName() ?: '';
                $isProjectSrc = (strpos($file, '/src/') !== false) && (strpos($file, '/packages/syntexa/') === false);
                if (!$isProjectSrc) {
                    continue;
                }
                $attrs = $rc->getAttributes(AsResponseOverride::class);
                if (empty($attrs)) {
                    continue;
                }
                /** @var AsResponseOverride $o */
                $o = $attrs[0]->newInstance();
                $overrides[] = ['meta' => $o, 'file' => $file, 'class' => $className];
            } catch (\Throwable $e) {
                echo "⚠️  Error analyzing response override {$className}: " . $e->getMessage() . "\n";
            }
        }
        if (empty($overrides)) {
            return;
        }
        usort($overrides, function ($a, $b) {
            return ($b['meta']->priority ?? 0) <=> ($a['meta']->priority ?? 0);
        });
        foreach ($overrides as $ov) {
            /** @var AsResponseOverride $meta */
            $meta = $ov['meta'];
            $target = $meta->of;
            $attrs = [];
            if ($meta->handle !== null) {
                $attrs['handle'] = EnvValueResolver::resolve($meta->handle);
            }
            if ($meta->format !== null) {
                $attrs['format'] = $meta->format; // Enum, no need to resolve
            }
            if ($meta->renderer !== null) {
                $attrs['renderer'] = EnvValueResolver::resolve($meta->renderer);
            }
            if ($meta->context !== null) {
                $attrs['context'] = EnvValueResolver::resolve($meta->context);
            }
            if (!empty($attrs)) {
                self::$responseAttrOverrides[$target] = $attrs;
            }
            echo "🔧 Collected response override for {$target}\n";
        }
    }
    
    public static function getResponseAttrOverride(string $class): ?array
    {
        return self::$responseAttrOverrides[$class] ?? null;
    }
    
    /**
     * Scan all PHP files in discovered modules (legacy method)
     */
    private static function scanAllAttributes(): void
    {
        $modules = ModuleRegistry::getModules();
        
        foreach ($modules as $module) {
            echo "🔍 Scanning module: {$module['name']} ({$module['type']})\n";
            
            $files = self::getAllPhpFiles($module['path']);
            
            // Legacy scanAllAttributes method is no longer used
            // All discovery is done via IntelligentAutoloader in scanAttributesIntelligently()
        }
    }
    
    /**
     * Get all PHP files recursively
     */
    private static function getAllPhpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    /**
     * Load class from file
     */
    private static function loadClassFromFile(string $file): ?ReflectionClass
    {
        // Skip vendor files
        if (strpos($file, '/vendor/') !== false) {
            return null;
        }
        
        // Extract namespace and class name from file
        $content = file_get_contents($file);
        
        if (!preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches)) {
            return null;
        }
        
        if (!preg_match('/class\s+(\w+)/', $content, $classMatches)) {
            return null;
        }
        
        $fullClassName = $namespaceMatches[1] . '\\' . $classMatches[1];
        
        // Load the file if class doesn't exist
        if (!class_exists($fullClassName)) {
            require_once $file;
        }
        
        // Check if class exists after loading
        if (!class_exists($fullClassName)) {
            return null;
        }
        
        return new ReflectionClass($fullClassName);
    }
    
}
