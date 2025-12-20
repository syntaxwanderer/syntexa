<?php

declare(strict_types=1);

namespace Syntexa\Orm\CodeGen;

use ReflectionClass;
use Syntexa\Orm\Attributes\AsEntity;
use Syntexa\Orm\Attributes\AsEntityPart;
use Syntexa\Core\IntelligentAutoloader;
use Syntexa\Core\ModuleRegistry;

/**
 * Generate Entity wrappers in src/modules/ for activation
 * Similar to RequestWrapperGenerator
 */
class EntityWrapperGenerator
{
    private const PROJECT_SRC = '/src/';
    private static bool $bootstrapped = false;
    private static array $cachedDefinitions = [];

    /**
     * Generate (or update) an entity wrapper for the given entity identifier.
     */
    public static function generate(string $identifier): void
    {
        $entities = self::bootstrapDefinitions();
        $target = self::resolveTarget($entities, $identifier);
        if ($target === null) {
            throw new \RuntimeException("Entity '{$identifier}' not found. Use a fully-qualified class name or unique short name.");
        }

        self::generateWrapper($target);
    }

    /**
     * Generate wrappers for every available entity definition
     */
    public static function generateAll(): void
    {
        $entities = self::bootstrapDefinitions();
        $total = 0;

        foreach ($entities as $target) {
            if (self::isProjectFile($target['file'] ?? '')) {
                continue;
            }
            self::generateWrapper($target);
            $total++;
        }

        if ($total === 0) {
            echo "ℹ️  No external entities found to generate.\n";
        } else {
            echo "✨ Generated {$total} entity wrapper(s).\n";
        }
    }

    /**
     * Bootstrap and get entity definitions (public for use in commands)
     */
    public static function bootstrapDefinitions(): array
    {
        if (!self::$bootstrapped) {
            IntelligentAutoloader::initialize();
            ModuleRegistry::initialize();
            self::$cachedDefinitions = self::collectEntityDefinitions();
            self::$bootstrapped = true;
        }

        return self::$cachedDefinitions;
    }

    /**
     * Resolve target entity from identifier (public for use in commands)
     */
    public static function resolveTarget(array $entities, string $identifier): ?array
    {
        // Try as FQN first
        if (isset($entities[$identifier])) {
            return $entities[$identifier];
        }

        // Try as short name
        foreach ($entities as $entity) {
            if ($entity['short'] === $identifier) {
                return $entity;
            }
        }

        return null;
    }

    private static function collectEntityDefinitions(): array
    {
        $definitions = [];
        $classes = IntelligentAutoloader::findClassesWithAttribute(AsEntity::class);

        foreach ($classes as $className) {
            $reflection = new ReflectionClass($className);
            $attr = $reflection->getAttributes(AsEntity::class)[0]->newInstance();
            $definitions[$className] = [
                'class' => $className,
                'short' => $reflection->getShortName(),
                'attr' => $attr,
                'file' => $reflection->getFileName() ?: '',
                'module' => self::detectModule($reflection->getFileName() ?: ''),
            ];
        }

        return $definitions;
    }

    private static function generateWrapper(array $target): void
    {
        $parts = self::collectEntityParts($target['class']);
        self::writeWrapper($target, $parts);
    }

    private static function collectEntityParts(string $baseClass): array
    {
        $parts = [];
        $traits = IntelligentAutoloader::findClassesWithAttribute(AsEntityPart::class);

        foreach ($traits as $traitClass) {
            $reflection = new ReflectionClass($traitClass);
            $attr = $reflection->getAttributes(AsEntityPart::class)[0]->newInstance();
            
            // Resolve base class reference
            $resolvedBase = self::resolveClassReference($attr->base, $traitClass);
            
            if ($resolvedBase === $baseClass) {
                $parts[] = [
                    'trait' => $traitClass,
                    'short' => $reflection->getShortName(),
                ];
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
        $contextReflection = new ReflectionClass($contextClass);
        $contextNamespace = $contextReflection->getNamespaceName();
        $resolved = $contextNamespace . '\\' . $reference;

        if (class_exists($resolved)) {
            return $resolved;
        }

        // Fallback: try as FQN
        if (class_exists($reference)) {
            return $reference;
        }

        return $reference; // Return as-is, will fail later if invalid
    }

    private static function detectModule(string $file): array
    {
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

    private static function toStudlyCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    private static function isProjectFile(string $file): bool
    {
        return str_contains($file, self::PROJECT_SRC);
    }


    private static function writeWrapper(array $target, array $parts): void
    {
        $module = $target['module'];
        $projectRoot = self::getProjectRoot();
        $outputDir = $projectRoot . self::PROJECT_SRC . "modules/{$module['studly']}/Entity";
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
        /** @var AsEntity $attr */
        $attr = $target['attr'];
        $imports = [];
        $usedAliases = [];

        $baseAlias = self::registerImport(
            $target['class'],
            $imports,
            $usedAliases,
            self::buildVendorAlias($target['class'], 'Base')
        );

        $attrParts = [
            "base: {$baseAlias}::class",
        ];

        if ($attr->table) {
            $attrParts[] = "table: '{$attr->table}'";
        }

        if ($attr->schema) {
            $attrParts[] = "schema: '{$attr->schema}'";
        }

        $attrString = implode(",\n    ", $attrParts);

        $traitAliases = self::registerTraitImports($traits, $imports, $usedAliases);
        $traitLines = array_map(
            static fn ($alias) => '    use ' . $alias . ';',
            array_filter($traitAliases)
        );
        $traitBlock = empty($traitLines) ? '' : "\n" . implode("\n", $traitLines) . "\n";

        // Entity wrapper extends base class (unlike Request which uses traits only)
        $extendsString = "extends {$baseAlias}";

        $namespace = 'Syntexa\\Modules\\' . ($target['module']['studly'] ?? 'Project') . '\\Entity';
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

use Syntexa\Orm\Attributes\AsEntity;
{$useBlock}

#[AsEntity(
    {$attrString}
)]
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
            
            // Build vendor-prefixed alias (like RequestWrapperGenerator)
            $vendor = $parts[0] ?? '';
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

