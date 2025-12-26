<?php

declare(strict_types=1);

namespace Syntexa\Core;

/**
 * Intelligent Autoloader that automatically discovers classes
 * 
 * This autoloader scans the entire project and automatically maps
 * namespaces to directories without any manual configuration.
 */
class IntelligentAutoloader
{
    private static array $classMap = [];
    private static bool $initialized = false;
    
    private static function allowNamespacePrefix(string $prefix): void
    {
        if ($prefix === '') {
            return;
        }
        $normalized = rtrim($prefix, '\\') . '\\';
        self::$allowedNamespacePrefixes[$normalized] = true;
    }
    
    private static function isNamespaceAllowed(string $namespace): bool
    {
        $normalized = rtrim($namespace, '\\') . '\\';
        foreach (array_keys(self::$allowedNamespacePrefixes) as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return true;
            }
        }
        return false;
    }
    private static array $allowedNamespacePrefixes = [
        'Syntexa\\' => true,
    ];
    
    /**
     * Initialize the intelligent autoloader
     */
    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }
        
        
        $startTime = microtime(true);
        self::buildClassMap();
        $endTime = microtime(true);
        
        
        // Register autoloader only if not already registered
        if (!in_array([self::class, 'autoload'], spl_autoload_functions())) {
            spl_autoload_register([self::class, 'autoload']);
        }
        
        self::$initialized = true;
    }
    
    /**
     * Build complete class map by scanning all PHP files
     */
    private static function buildClassMap(): void
    {
        $projectRoot = self::getProjectRoot();
        
        // Scan all relevant directories
        $directories = [
            $projectRoot . '/src',
            $projectRoot . '/vendor/syntexa'
        ];
        
        // Also scan packages directory for local development
        if (is_dir($projectRoot . '/packages')) {
            $directories[] = $projectRoot . '/packages';
        }
        
        // Always scan src/modules for local custom modules
        if (is_dir($projectRoot . '/src/modules')) {
            $directories[] = $projectRoot . '/src/modules';
        }
        
        // Include module-specific PSR-4 paths (may use non-Syntexa namespaces)
        $moduleMappings = self::getModuleAutoloadMappings();
        foreach ($moduleMappings as $prefix => $paths) {
            self::allowNamespacePrefix($prefix);
            foreach ($paths as $path) {
                $directories[] = $path;
            }
        }
        
        $directories = array_values(array_unique($directories));
        
        // Add Syntexa\Core classes manually
        self::addSyntexaCoreClasses();
        
        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                self::scanDirectory($directory);
            }
        }
    }
    
    /**
     * Scan directory recursively for PHP files
     */
    private static function scanDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                self::analyzeFile($file->getPathname());
            }
        }
    }
    
    /**
     * Analyze PHP file and extract class information
     */
    private static function analyzeFile(string $filePath): void
    {
        try {
            $content = file_get_contents($filePath);
            
            // Extract namespace
            if (!preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches)) {
                return; // No namespace found
            }
            
            $namespace = $namespaceMatches[1];
            if (!self::isNamespaceAllowed($namespace)) {
                return;
            }
            
            // Extract class/trait/interface name
            if (!preg_match('/\b(class|interface|trait)\s+(\w+)/', $content, $classMatches)) {
                return; // No declaration found
            }
            
            $className = $classMatches[2];
            $fullClassName = $namespace . '\\' . $className;
            
            // Store in class map
            self::$classMap[$fullClassName] = $filePath;
            
        } catch (\Throwable $e) {
            // Skip files that can't be analyzed
        }
    }
    
    /**
     * Autoload function
     */
    public static function autoload(string $className): void
    {
        if (isset(self::$classMap[$className])) {
            require_once self::$classMap[$className];
        }
    }
    
    /**
     * Get all discovered classes
     */
    public static function getClassMap(): array
    {
        return self::$classMap;
    }
    
    /**
     * Get classes by namespace prefix
     */
    public static function getClassesByNamespace(string $namespacePrefix): array
    {
        return array_filter(
            self::$classMap,
            fn($className) => str_starts_with($className, $namespacePrefix),
            ARRAY_FILTER_USE_KEY
        );
    }
    
    /**
     * Get all namespaces
     */
    public static function getNamespaces(): array
    {
        $namespaces = [];
        
        foreach (array_keys(self::$classMap) as $className) {
            $namespace = substr($className, 0, strrpos($className, '\\'));
            $namespaces[$namespace] = true;
        }
        
        return array_keys($namespaces);
    }
    
    /**
     * Find classes with specific attributes
     */
    public static function findClassesWithAttribute(string $attributeClass): array
    {
        // Ensure autoloader is initialized
        if (!self::$initialized) {
            self::initialize();
        }
        
        $classes = [];
        $classMapSize = count(self::$classMap);
        
        foreach (self::$classMap as $className => $filePath) {
            
            // Load class if not already loaded
            if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
                if (file_exists($filePath)) {
                    try {
                        require_once $filePath;
                    } catch (\Throwable $e) {
                        // Skip classes that can't be loaded (e.g., wrapper classes with circular dependencies)
                        if (str_contains($e->getMessage(), 'not found') && str_contains($e->getMessage(), 'Syntexa\\Modules\\')) {
                            continue;
                        }
                        // For other errors, continue to reflection check (might be recoverable)
                    }
                }
            }
            
            if (class_exists($className) || interface_exists($className) || trait_exists($className)) {
                try {
                    $reflection = new \ReflectionClass($className);
                    
                    if ($reflection->getAttributes($attributeClass)) {
                        $classes[] = $className;
                    }
                } catch (\Throwable $e) {
                    // Skip classes that can't be reflected
                }
            }
        }
        
        return $classes;
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
    
    /**
     * Generate PSR-4 mappings automatically
     */
    public static function generatePsr4Mappings(): array
    {
        $mappings = [];
        $namespaces = self::getNamespaces();
        
        foreach ($namespaces as $namespace) {
            // Find the best directory for this namespace
            $directory = self::findBestDirectory($namespace);
            
            if ($directory) {
                $mappings[$namespace . '\\'] = $directory;
            }
        }
        
        return $mappings;
    }
    
    /**
     * Find the best directory for a namespace
     */
    private static function findBestDirectory(string $namespace): ?string
    {
        $classes = self::getClassesByNamespace($namespace);
        
        if (empty($classes)) {
            return null;
        }
        
        // Get the first class file to determine directory
        $firstClass = array_values($classes)[0];
        $filePath = $firstClass;
        
        // Extract directory from file path
        $projectRoot = self::getProjectRoot();
        $relativePath = str_replace($projectRoot . '/', '', dirname($filePath));
        
        // Special handling for Syntexa\Core namespace
        if ($namespace === 'Syntexa\\Core') {
            return 'vendor/syntexa/core/src/';
        }
        
        return $relativePath . '/';
    }
    
    /**
     * Add Syntexa\Core classes manually
     */
    private static function addSyntexaCoreClasses(): void
    {
        $coreClasses = [
            'Syntexa\\Core\\Application' => 'vendor/syntexa/core/src/Application.php',
            'Syntexa\\Core\\Environment' => 'vendor/syntexa/core/src/Environment.php',
            'Syntexa\\Core\\ErrorHandler' => 'vendor/syntexa/core/src/ErrorHandler.php',
            'Syntexa\\Core\\Request' => 'vendor/syntexa/core/src/Request.php',
            'Syntexa\\Core\\RequestFactory' => 'vendor/syntexa/core/src/RequestFactory.php',
            'Syntexa\\Core\\Response' => 'vendor/syntexa/core/src/Response.php',
            'Syntexa\\Core\\ModuleRegistry' => 'vendor/syntexa/core/src/ModuleRegistry.php',
            'Syntexa\\Core\\AutoloaderGenerator' => 'vendor/syntexa/core/src/AutoloaderGenerator.php',
            'Syntexa\\Core\\IntelligentAutoloader' => 'vendor/syntexa/core/src/IntelligentAutoloader.php',
            'Syntexa\\Core\\Discovery\\AttributeDiscovery' => 'vendor/syntexa/core/src/Discovery/AttributeDiscovery.php'
        ];
        
        $projectRoot = self::getProjectRoot();
        
        foreach ($coreClasses as $className => $relativePath) {
            $fullPath = $projectRoot . '/' . $relativePath;
            if (file_exists($fullPath)) {
                self::$classMap[$className] = $fullPath;
            }
        }
    }

    private static function getModuleAutoloadMappings(): array
    {
        if (!class_exists(ModuleRegistry::class)) {
            return [];
        }
        ModuleRegistry::initialize();
        return ModuleRegistry::getModuleAutoloadMappings();
    }
}
