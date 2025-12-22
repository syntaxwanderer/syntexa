<?php

declare(strict_types=1);

namespace Syntexa\Orm\CodeGen;

use ReflectionClass;
use Syntexa\Orm\Attributes\AsEntity;
use Syntexa\Orm\Attributes\AsDomainPart;
use Syntexa\Core\IntelligentAutoloader;
use Syntexa\Core\ModuleRegistry;

/**
 * Generate Domain model wrappers in src/modules/ for cross-module domain extensions
 * Similar to EntityWrapperGenerator but for domain models
 */
class DomainWrapperGenerator
{
    private const PROJECT_SRC = '/src/';
    private static bool $bootstrapped = false;
    private static array $cachedDefinitions = [];

    /**
     * Generate (or update) a domain wrapper for the given domain class identifier.
     */
    public static function generate(string $identifier): void
    {
        $domains = self::bootstrapDefinitions();
        $target = self::resolveTarget($domains, $identifier);
        if ($target === null) {
            throw new \RuntimeException("Domain class '{$identifier}' not found. Use a fully-qualified class name or unique short name.");
        }

        self::generateWrapper($target);
    }

    /**
     * Generate wrappers for every available domain class definition
     */
    public static function generateAll(): void
    {
        $domains = self::bootstrapDefinitions();
        $total = 0;
        $errors = [];

        foreach ($domains as $target) {
            if (self::isProjectFile($target['file'] ?? '')) {
                continue;
            }
            
            try {
                self::generateWrapper($target);
                $total++;
            } catch (\Throwable $e) {
                // Skip errors for wrapper classes that reference themselves
                // This can happen when wrapper was just generated and autoloader tries to load it
                if (str_contains($e->getMessage(), 'not found') && str_contains($e->getMessage(), 'Syntexa\\Modules\\')) {
                    // Silently skip - wrapper will be generated on next run
                    continue;
                }
                $errors[] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo "⚠️  Warning: {$error}\n";
            }
        }

        if ($total === 0) {
            echo "ℹ️  No external domain classes found to generate.\n";
        } else {
            echo "✨ Generated {$total} domain wrapper(s).\n";
        }
    }

    private static function bootstrapDefinitions(): array
    {
        if (!self::$bootstrapped) {
            try {
                IntelligentAutoloader::initialize();
                ModuleRegistry::initialize();
                self::$cachedDefinitions = self::collectDomainDefinitions();
            } catch (\Throwable $e) {
                // If autoloader fails (e.g., wrapper class not found), try again with error suppression
                // This can happen when wrapper was just generated and autoloader tries to load it
                if (str_contains($e->getMessage(), 'not found') && str_contains($e->getMessage(), 'Syntexa\\Modules\\')) {
                    // Clear cache and try again
                    self::$cachedDefinitions = [];
                    // Re-initialize autoloader to clear any cached errors
                    IntelligentAutoloader::initialize();
                    ModuleRegistry::initialize();
                    self::$cachedDefinitions = self::collectDomainDefinitions();
                } else {
                    throw $e;
                }
            }
            self::$bootstrapped = true;
        }

        return self::$cachedDefinitions;
    }

    /**
     * Collect all domain classes from storage entities that have domainClass configured
     */
    private static function collectDomainDefinitions(): array
    {
        $definitions = [];
        
        try {
            $classes = IntelligentAutoloader::findClassesWithAttribute(AsEntity::class);
        } catch (\Throwable $e) {
            // If autoloader fails (e.g., wrapper class not found), return empty definitions
            // This can happen when wrapper was just generated and autoloader tries to load it
            if (str_contains($e->getMessage(), 'not found') && str_contains($e->getMessage(), 'Syntexa\\Modules\\')) {
                return [];
            }
            throw $e;
        }

        foreach ($classes as $storageClass) {
            try {
                $reflection = new ReflectionClass($storageClass);
                $attr = $reflection->getAttributes(AsEntity::class)[0]->newInstance();
                
                // Only process entities that have domainClass configured
                if ($attr->domainClass === null) {
                    continue;
                }

                $domainClass = $attr->domainClass;
                
                // Skip if domain class doesn't exist
                // But skip wrapper classes (they are in src/modules/)
                if (str_contains($domainClass, 'Syntexa\\Modules\\')) {
                    continue;
                }
                
                if (!class_exists($domainClass)) {
                    continue;
                }

                $domainReflection = new ReflectionClass($domainClass);
                
                // Skip if domain class file is in project src/modules (it's already a wrapper)
                $domainFile = $domainReflection->getFileName() ?: '';
                if (self::isProjectFile($domainFile)) {
                    continue;
                }
                
                // Detect module from domain class namespace or storage class file
                $module = self::detectModuleFromDomain($domainClass, $storageClass);
                
                $definitions[$domainClass] = [
                    'domainClass' => $domainClass,
                    'storageClass' => $storageClass,
                    'short' => $domainReflection->getShortName(),
                    'file' => $domainFile,
                    'module' => $module,
                ];
            } catch (\Throwable $e) {
                // Skip entities that can't be processed (e.g., wrapper classes)
                if (str_contains($e->getMessage(), 'not found') && str_contains($e->getMessage(), 'Syntexa\\Modules\\')) {
                    continue;
                }
                // Re-throw other errors
                throw $e;
            }
        }

        return $definitions;
    }

    private static function generateWrapper(array $target): void
    {
        $parts = self::collectDomainParts($target['domainClass']);
        self::writeWrapper($target, $parts);
    }

    private static function collectDomainParts(string $baseDomainClass): array
    {
        $parts = [];
        
        try {
            $traits = IntelligentAutoloader::findClassesWithAttribute(AsDomainPart::class);
        } catch (\Throwable $e) {
            // If autoloader fails (e.g., wrapper class not found), return empty parts
            // This can happen when wrapper was just generated and autoloader tries to load it
            if (str_contains($e->getMessage(), 'not found') && str_contains($e->getMessage(), 'Syntexa\\Modules\\')) {
                return [];
            }
            throw $e;
        }

        foreach ($traits as $traitClass) {
            try {
                $reflection = new ReflectionClass($traitClass);
                if (!$reflection->isTrait()) {
                    continue;
                }
                
                $attr = $reflection->getAttributes(AsDomainPart::class)[0]->newInstance();
                
                // Resolve base class reference
                $resolvedBase = self::resolveClassReference($attr->base, $traitClass);
                
                if ($resolvedBase === $baseDomainClass) {
                    $parts[] = [
                        'trait' => $traitClass,
                        'short' => $reflection->getShortName(),
                    ];
                }
            } catch (\Throwable $e) {
                // Skip traits that can't be loaded (e.g., wrapper classes)
                if (str_contains($e->getMessage(), 'not found') && str_contains($e->getMessage(), 'Syntexa\\Modules\\')) {
                    continue;
                }
                // Re-throw other errors
                throw $e;
            }
        }

        return $parts;
    }

    private static function resolveClassReference(string $reference, string $contextClass): string
    {
        if (str_starts_with($reference, '\\')) {
            return ltrim($reference, '\\');
        }

        // Try to resolve relative to context class namespace
        try {
            $contextReflection = new ReflectionClass($contextClass);
            $contextNamespace = $contextReflection->getNamespaceName();
            $resolved = $contextNamespace . '\\' . $reference;

            // Skip wrapper classes (they are in src/modules/)
            if (!str_contains($resolved, 'Syntexa\\Modules\\')) {
                if (class_exists($resolved)) {
                    return $resolved;
                }
            }
        } catch (\Throwable) {
            // Ignore reflection errors
        }

        // Fallback: try as FQN (but skip wrapper classes)
        if (!str_contains($reference, 'Syntexa\\Modules\\')) {
            if (class_exists($reference)) {
                return $reference;
            }
        }

        return $reference; // Return as-is, will fail later if invalid
    }

    /**
     * Detect module from domain class namespace or storage class file
     */
    private static function detectModuleFromDomain(string $domainClass, string $storageClass): array
    {
        // Try to extract module name from domain class namespace
        // e.g., Syntexa\UserFrontend\Domain\Entity\User -> UserFrontend
        // e.g., Syntexa\Modules\UserFrontend\Domain\User -> UserFrontend (wrapper)
        if (preg_match('/Syntexa\\\\(?:Modules\\\\)?([A-Z][a-zA-Z0-9]*)\\\\Domain/', $domainClass, $matches)) {
            $moduleName = $matches[1];
            // Convert to kebab-case for module name lookup
            $kebabName = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $moduleName));
            
            // Try to find in ModuleRegistry
            foreach (ModuleRegistry::getModules() as $module) {
                if ($module['name'] === $kebabName || str_contains($module['name'], $kebabName)) {
                    return [
                        'vendor' => $module['namespace'] ?? 'Project',
                        'name' => $module['name'] ?? $kebabName,
                        'studly' => $moduleName, // Use original StudlyCase from namespace
                    ];
                }
            }
            
            // If not found in registry, use the namespace module name
            return [
                'vendor' => 'Project',
                'name' => $kebabName,
                'studly' => $moduleName,
            ];
        }
        
        // Fallback: detect from storage class file
        $reflection = new ReflectionClass($storageClass);
        $file = $reflection->getFileName() ?: '';
        
        // Try to find module using ModuleRegistry
        foreach (ModuleRegistry::getModules() as $module) {
            $path = $module['path'] ?? null;
            if ($path && str_starts_with($file, rtrim($path, '/') . '/')) {
                $moduleName = $module['name'] ?? 'project';
                return [
                    'vendor' => $module['namespace'] ?? 'Project',
                    'name' => $moduleName,
                    'studly' => self::slugToStudly($moduleName),
                ];
            }
        }
        
        // Last fallback: try to detect from file path
        $parts = explode(DIRECTORY_SEPARATOR, $file);
        $moduleIndex = array_search('packages', $parts);
        
        if ($moduleIndex !== false && isset($parts[$moduleIndex + 2])) {
            $vendor = $parts[$moduleIndex + 1];
            $module = $parts[$moduleIndex + 2];
            return [
                'vendor' => $vendor,
                'name' => $module,
                'studly' => self::toStudlyCase($module),
            ];
        }

        return ['vendor' => 'Project', 'name' => 'project', 'studly' => 'Project'];
    }

    private static function slugToStudly(string $slug): string
    {
        $parts = preg_split('/[-_]/', $slug);
        $parts = array_map(static fn ($p) => ucfirst(strtolower($p)), $parts);
        return implode('', $parts);
    }

    private static function toStudlyCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    private static function isProjectFile(string $file): bool
    {
        // Check if file is in src/modules/ (wrapper) or src/infrastructure/ (generated)
        // But allow files in packages/ (module source)
        return str_contains($file, self::PROJECT_SRC . 'modules/') 
            || str_contains($file, self::PROJECT_SRC . 'infrastructure/');
    }

    private static function resolveTarget(array $domains, string $identifier): ?array
    {
        // Try as FQN first
        if (isset($domains[$identifier])) {
            return $domains[$identifier];
        }

        // Try as short name
        foreach ($domains as $domain) {
            if ($domain['short'] === $identifier) {
                return $domain;
            }
        }

        return null;
    }

    private static function writeWrapper(array $target, array $parts): void
    {
        $module = $target['module'];
        $projectRoot = self::getProjectRoot();
        $outputDir = $projectRoot . self::PROJECT_SRC . "modules/{$module['studly']}/Domain";
        @mkdir($outputDir, 0755, true);

        $outputFile = $outputDir . '/' . $target['short'] . '.php';

        // Merge with existing traits
        $existingTraits = self::extractExistingTraits($outputFile);

        $traitList = [];
        foreach ($existingTraits as $trait) {
            $traitList[$trait] = true;
        }
        foreach ($parts as $part) {
            $traitList['\\' . ltrim($part['trait'], '\\')] = true;
        }

        $finalTraits = array_keys($traitList);

        $content = self::renderTemplate($target, $finalTraits);
        file_put_contents($outputFile, $content);

        echo "✅ Generated {$outputFile}\n";
    }

    private static function extractExistingTraits(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }

        preg_match_all('/^\s{4}use\s+\\\\?([\w\\\\]+)\s*;/m', $content, $matches);
        $rawTraits = $matches[1] ?? [];
        $traits = array_values(array_filter(
            $rawTraits,
            static fn ($trait) => str_contains($trait, '\\')
        ));

        return array_map(
            static fn ($trait) => '\\' . ltrim($trait, '\\'),
            $traits
        );
    }

    private static function renderTemplate(array $target, array $traits): string
    {
        $imports = [];
        $usedAliases = [];

        $baseAlias = self::registerImport(
            $target['domainClass'],
            $imports,
            $usedAliases,
            self::buildVendorAlias($target['domainClass'], 'Base')
        );

        $traitAliases = self::registerTraitImports($traits, $imports, $usedAliases);
        $traitLines = array_map(
            static fn ($alias) => '    use ' . $alias . ';',
            array_filter($traitAliases)
        );
        $traitBlock = empty($traitLines) ? '' : "\n" . implode("\n", $traitLines) . "\n";

        // Domain wrapper extends base domain class
        $extendsString = "extends {$baseAlias}";

        $namespace = 'Syntexa\\Modules\\' . ($target['module']['studly'] ?? 'Project') . '\\Domain';
        $className = $target['short'];

        $header = <<<'PHP'
<?php

declare(strict_types=1);

PHP;

        $useLines = array_map(
            static fn ($data) => 'use ' . $data['fqn'] . ($data['alias'] !== $data['short'] ? ' as ' . $data['alias'] : '') . ';',
            self::uniqueImports($imports)
        );
        $useBlock = empty($useLines) ? '' : implode("\n", $useLines) . "\n";

        $body = <<<PHP
namespace {$namespace};

{$useBlock}
/**
 * Domain wrapper to apply cross-module domain extensions.
 * Base domain lives in module: {$target['domainClass']}
 */
class {$className} {$extendsString}
{
{$traitBlock}}

PHP;

        return $header . $body;
    }

    private static function registerImport(
        string $fqn,
        array &$imports,
        array &$usedAliases,
        ?string $alias = null
    ): string {
        $parts = explode('\\', $fqn);
        $short = end($parts);
        $alias = $alias ?? $short;

        if (!isset($imports[$fqn])) {
            $imports[$fqn] = [
                'fqn' => $fqn,
                'short' => $short,
                'alias' => $alias,
            ];
            $usedAliases[$alias] = true;
        }

        return $alias;
    }

    private static function registerTraitImports(array $traits, array &$imports, array &$usedAliases): array
    {
        $aliases = [];
        foreach ($traits as $trait) {
            $trait = ltrim($trait, '\\');
            $parts = explode('\\', $trait);
            $short = end($parts);
            
            // Build vendor-prefixed alias
            $alias = self::buildVendorAlias($trait, 'Trait');
            
            $aliases[] = self::registerImport($trait, $imports, $usedAliases, $alias);
        }
        return $aliases;
    }

    private static function buildVendorAlias(string $fqn, string $suffix): string
    {
        $parts = explode('\\', ltrim($fqn, '\\'));
        $vendor = $parts[0] ?? '';
        $className = end($parts);
        
        return $vendor . $className . $suffix;
    }

    private static function uniqueImports(array $imports): array
    {
        $unique = [];
        foreach ($imports as $import) {
            $key = $import['fqn'];
            if (!isset($unique[$key])) {
                $unique[$key] = $import;
            }
        }
        return array_values($unique);
    }

    private static function getProjectRoot(): string
    {
        $dir = __DIR__;
        while ($dir !== '/' && $dir !== '') {
            if (file_exists($dir . '/composer.json')) {
                if (is_dir($dir . '/src/modules')) {
                    return $dir;
                }
            }
            $dir = dirname($dir);
        }

        return dirname(__DIR__, 6); // Fallback
    }
}

