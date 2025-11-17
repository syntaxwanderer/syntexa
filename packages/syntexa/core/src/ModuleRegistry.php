<?php

declare(strict_types=1);

namespace Syntexa\Core;

/**
 * Module Registry for managing different types of modules
 * 
 * This class handles discovery and registration of:
 * - Local modules (src/modules/)
 * - Composer modules (src/packages/)
 * - Vendor modules (vendor/)
 */
class ModuleRegistry
{
    private static array $modules = [];
    private static bool $initialized = false;
    
    /**
     * Initialize the module registry
     */
    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }
        
        echo "🔍 Discovering modules...\n";
        
        $startTime = microtime(true);
        self::discoverModules();
        $endTime = microtime(true);
        
        echo "✅ Found " . count(self::$modules) . " modules\n";
        echo "⏱️  Module discovery took " . round(($endTime - $startTime) * 1000, 2) . "ms\n";
        
        self::$initialized = true;
    }
    
    /**
     * Get all discovered modules
     */
    public static function getModules(): array
    {
        return self::$modules;
    }
    
    /**
     * Get modules by type
     */
    public static function getModulesByType(string $type): array
    {
        return array_filter(self::$modules, fn($module) => $module['type'] === $type);
    }
    
    /**
     * Get local modules
     */
    public static function getLocalModules(): array
    {
        return self::getModulesByType('local');
    }
    
    /**
     * Get composer modules
     */
    public static function getComposerModules(): array
    {
        return self::getModulesByType('composer');
    }
    
    /**
     * Get vendor modules
     */
    public static function getVendorModules(): array
    {
        return self::getModulesByType('vendor');
    }
    
    /**
     * Discover all modules
     */
    private static function discoverModules(): void
    {
        $projectRoot = self::getProjectRoot();
        
        // Discover local modules
        $localModules = self::discoverLocalModules($projectRoot);
        foreach ($localModules as $module) {
            self::registerModule($module['path'], $module['name'], 'local', $module['namespace']);
        }
        
        // Discover composer modules
        $composerModules = self::discoverComposerModules($projectRoot);
        foreach ($composerModules as $module) {
            self::registerModule($module['path'], $module['name'], 'composer', $module['namespace']);
        }
        
        // Discover vendor modules (optional)
        $vendorModules = self::discoverVendorModules($projectRoot);
        foreach ($vendorModules as $module) {
            self::registerModule($module['path'], $module['name'], 'vendor', $module['namespace']);
        }
    }
    
    /**
     * Discover local modules in src/modules/
     */
    private static function discoverLocalModules(string $projectRoot): array
    {
        $modules = [];
        $modulesPath = $projectRoot . '/src/modules';
        
        if (!is_dir($modulesPath)) {
            return $modules;
        }
        
        $directories = glob($modulesPath . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            $moduleName = basename($dir);
            $namespace = "Syntexa\\Modules\\" . ucfirst($moduleName);
            
            $modules[] = [
                'path' => $dir,
                'name' => $moduleName,
                'namespace' => $namespace
            ];
        }
        
        return $modules;
    }
    
    /**
     * Discover composer modules in src/packages/
     */
    private static function discoverComposerModules(string $projectRoot): array
    {
        $modules = [];
        $packagesPath = $projectRoot . '/src/packages';
        
        if (!is_dir($packagesPath)) {
            return $modules;
        }
        
        $directories = glob($packagesPath . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            $moduleName = basename($dir);
            $namespace = "Syntexa\\Packages\\" . ucfirst($moduleName);
            
            $modules[] = [
                'path' => $dir,
                'name' => $moduleName,
                'namespace' => $namespace
            ];
        }
        
        return $modules;
    }
    
    /**
     * Discover vendor modules (optional)
     */
    private static function discoverVendorModules(string $projectRoot): array
    {
        $modules = [];
        
        // First, check packages/syntexa/ (local development)
        $packagesPath = $projectRoot . '/packages/syntexa';
        if (is_dir($packagesPath)) {
            $syntexaPackages = glob($packagesPath . '/*', GLOB_ONLYDIR);
            foreach ($syntexaPackages as $dir) {
                $packageName = basename($dir);
                // Extract module name from package name (e.g., "module-user-frontend" -> "module-user-frontend")
                $namespace = "Syntexa\\" . str_replace('-', '', ucwords($packageName, '-'));
                
                $modules[] = [
                    'path' => $dir,
                    'name' => $packageName,
                    'namespace' => $namespace
                ];
            }
        }
        
        // Then, check vendor/syntexa/ (installed packages or symlinks)
        $vendorPath = $projectRoot . '/vendor';
        if (is_dir($vendorPath)) {
            $syntexaPackages = glob($vendorPath . '/syntexa/*', GLOB_ONLYDIR);
            foreach ($syntexaPackages as $dir) {
                // Resolve symlink to real path
                $realPath = is_link($dir) ? readlink($dir) : $dir;
                if (!str_starts_with($realPath, '/')) {
                    // Relative symlink
                    $realPath = dirname($dir) . '/' . $realPath;
                }
                // Use real path for module registration
                $resolvedPath = realpath($realPath) ?: $dir;
                
                $packageName = basename($dir);
                $namespace = "Syntexa\\" . ucfirst($packageName);
                
                $modules[] = [
                    'path' => $resolvedPath,
                    'name' => $packageName,
                    'namespace' => $namespace
                ];
            }
        }
        
        return $modules;
    }
    
    /**
     * Register a module
     */
    private static function registerModule(string $path, string $name, string $type, string $namespace): void
    {
        // Filter by composer "type": only accept syntexa-module
        $composerType = self::readComposerType($path);
        if (!in_array($composerType, ['syntexa-module', 'syntexa-theme'], true)) {
            // Skip non-syntexa modules/packages
            return;
        }

        $meta = self::readComposerMeta($path);

        // Default template path inside module
        $defaultTemplatePath = $path . '/src/Application/View/templates';

        // Aliases
        $aliases = [];
        $aliases[] = $name;
        $friendly = $name;
        foreach (["syntexa-module-", "module-"] as $prefix) {
            if (str_starts_with($friendly, $prefix)) {
                $friendly = substr($friendly, strlen($prefix));
            }
        }
        if ($friendly !== $name) {
            $aliases[] = $friendly;
        }
        if (!empty($meta['template_alias'])) {
            $aliases[] = (string)$meta['template_alias'];
        }
        $aliases = array_values(array_unique($aliases));

        // Template paths
        $templatePaths = [];
        if (is_dir($defaultTemplatePath)) {
            $templatePaths[] = $defaultTemplatePath;
        }
        foreach ($meta['template_paths'] as $rel) {
            $p = $path . '/' . ltrim($rel, '/');
            if (is_dir($p)) {
                $templatePaths[] = $p;
            }
        }

        // Check if module with same path already registered (avoid duplicates from symlinks)
        $realPath = realpath($path) ?: $path;
        foreach (self::$modules as $existing) {
            $existingRealPath = realpath($existing['path']) ?: $existing['path'];
            if ($existingRealPath === $realPath) {
                // Module already registered, skip
                return;
            }
        }
        
        self::$modules[] = [
            'path' => $realPath,
            'name' => $name,
            'type' => $type,
            'namespace' => $namespace,
            'composerType' => $composerType,
            'aliases' => $aliases,
            'templatePaths' => $templatePaths,
            'controllers' => self::findControllers($path, $namespace),
            'routes' => self::findRoutes($path, $namespace)
        ];
        
        echo "📦 Registered {$type} module: {$name} ({$namespace})\n";
    }

    private static function readComposerType(string $modulePath): ?string
    {
        $composerJson = $modulePath . '/composer.json';
        if (!is_file($composerJson)) {
            return null;
        }
        try {
            $json = json_decode((string)file_get_contents($composerJson), true, 512, JSON_THROW_ON_ERROR);
            return $json['type'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function readComposerMeta(string $modulePath): array
    {
        $meta = [
            'template_alias' => null,
            'template_paths' => []
        ];
        $composerJson = $modulePath . '/composer.json';
        if (!is_file($composerJson)) {
            return $meta;
        }
        try {
            $json = json_decode((string)file_get_contents($composerJson), true, 512, JSON_THROW_ON_ERROR);
            $extra = $json['extra']['syntexa-module'] ?? [];
            if (!empty($extra['template_alias']) && is_string($extra['template_alias'])) {
                $meta['template_alias'] = $extra['template_alias'];
            }
            if (!empty($extra['template_paths']) && is_array($extra['template_paths'])) {
                $meta['template_paths'] = $extra['template_paths'];
            }
        } catch (\Throwable $e) {
            // ignore invalid json
        }
        return $meta;
    }
    
    /**
     * Find controllers in module
     */
    private static function findControllers(string $path, string $namespace): array
    {
        $controllers = [];
        $files = glob($path . '/*Controller.php');
        
        foreach ($files as $file) {
            $className = basename($file, '.php');
            $controllers[] = $namespace . '\\' . $className;
        }
        
        return $controllers;
    }
    
    /**
     * Find routes in module
     */
    private static function findRoutes(string $path, string $namespace): array
    {
        // This will be implemented when we integrate with AttributeDiscovery
        return [];
    }
    
    /**
     * Get project root directory
     */
    private static function getProjectRoot(): string
    {
        $currentDir = __DIR__;
        
        // Go up from vendor/syntexa/core/src/ to project root
        return dirname($currentDir, 4);
    }
}
