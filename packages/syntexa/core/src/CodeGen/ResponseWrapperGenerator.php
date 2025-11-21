<?php

declare(strict_types=1);

namespace Syntexa\Core\CodeGen;

use ReflectionClass;
use Syntexa\Core\Attributes\AsResponse;
use Syntexa\Core\Attributes\AsResponsePart;
use Syntexa\Core\Config\EnvValueResolver;
use Syntexa\Core\IntelligentAutoloader;
use Syntexa\Core\ModuleRegistry;

class ResponseWrapperGenerator
{
    private static bool $bootstrapped = false;
    private static array $cachedDefinitions = [];

    public static function generate(string $identifier): void
    {
        $responses = self::bootstrapDefinitions();
        $target = self::resolveTarget($responses, $identifier);
        if ($target === null) {
            throw new \RuntimeException("Response '{$identifier}' not found. Use a fully-qualified class name or unique short name.");
        }

        self::generateWrapper($target);
    }

    public static function generateAll(): void
    {
        $responses = self::bootstrapDefinitions();
        $total = 0;

        foreach ($responses as $target) {
            if (self::isProjectFile($target['file'] ?? '')) {
                continue;
            }
            self::generateWrapper($target);
            $total++;
        }

        if ($total === 0) {
            echo "ℹ️  No external responses found to generate.\n";
        } else {
            echo "✨ Generated {$total} response wrapper(s).\n";
        }
    }

    private static function bootstrapDefinitions(): array
    {
        if (!self::$bootstrapped) {
            IntelligentAutoloader::initialize();
            ModuleRegistry::initialize();
            self::$cachedDefinitions = self::collectResponseDefinitions();
            self::$bootstrapped = true;
        }

        return self::$cachedDefinitions;
    }

    private static function collectResponseDefinitions(): array
    {
        $definitions = [];
        $classes = IntelligentAutoloader::findClassesWithAttribute(AsResponse::class);

        foreach ($classes as $className) {
            $reflection = new ReflectionClass($className);
            $attrs = $reflection->getAttributes(AsResponse::class);
            if (empty($attrs)) {
                continue;
            }
            $definitions[$className] = [
                'class' => $className,
                'short' => $reflection->getShortName(),
                'attr' => $attrs[0]->newInstance(),
                'file' => $reflection->getFileName() ?: '',
                'module' => self::detectModule($reflection->getFileName() ?: ''),
                'parent' => $reflection->getParentClass() ? $reflection->getParentClass()->getName() : null,
                'interfaces' => $reflection->getInterfaceNames(),
            ];
        }

        return $definitions;
    }

    private static function generateWrapper(array $target): void
    {
        $parts = self::collectResponseParts($target['class']);
        self::writeWrapper($target, $parts);
    }

    private static function collectResponseParts(string $baseClass): array
    {
        $parts = [];
        $classes = IntelligentAutoloader::findClassesWithAttribute(AsResponsePart::class);

        foreach ($classes as $className) {
            $reflection = new ReflectionClass($className);
            if ($reflection->isTrait() === false) {
                continue;
            }
            $attributes = $reflection->getAttributes(AsResponsePart::class);
            foreach ($attributes as $attribute) {
                /** @var AsResponsePart $meta */
                $meta = $attribute->newInstance();
                if ($meta->base === $baseClass) {
                    $parts[] = [
                        'trait' => $className,
                        'file' => $reflection->getFileName() ?: '',
                    ];
                }
            }
        }

        return $parts;
    }

    private static function resolveTarget(array $responses, string $identifier): ?array
    {
        if (isset($responses[$identifier])) {
            return $responses[$identifier];
        }

        $matches = array_values(array_filter(
            $responses,
            fn ($item) => strcasecmp($item['short'], $identifier) === 0
        ));

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            $list = implode("\n - ", array_map(fn ($item) => $item['class'], $matches));
            throw new \RuntimeException("Ambiguous short name '{$identifier}'. Matches:\n - {$list}");
        }

        return null;
    }

    private static function detectModule(string $file): array
    {
        $default = [
            'name' => 'project',
            'studly' => 'Project',
            'path' => null,
        ];

        if ($file === '') {
            return $default;
        }

        foreach (ModuleRegistry::getModules() as $module) {
            $path = $module['path'] ?? null;
            if ($path && str_starts_with($file, rtrim($path, '/') . '/')) {
                return [
                    'name' => $module['name'] ?? 'module',
                    'studly' => self::slugToStudly($module['name'] ?? 'module'),
                    'path' => $path,
                ];
            }
        }

        return $default;
    }

    private static function slugToStudly(string $slug): string
    {
        $parts = preg_split('/[-_]/', $slug);
        $parts = array_map(static fn ($p) => ucfirst(strtolower($p)), $parts);

        return implode('', $parts);
    }

    private static function isProjectFile(?string $file): bool
    {
        if ($file === null || $file === '') {
            return false;
        }
        $projectRoot = dirname(__DIR__, 5);
        return str_starts_with($file, $projectRoot . '/src/');
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

    private static function writeWrapper(array $target, array $parts): void
    {
        $projectRoot = dirname(__DIR__, 5);
        $moduleStudly = $target['module']['studly'] ?? 'Project';
        $outputDir = $projectRoot . '/src/modules/' . $moduleStudly . '/Output';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $outputFile = $outputDir . '/' . $target['short'] . '.php';
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

    private static function renderTemplate(array $target, array $traits): string
    {
        /** @var AsResponse $attr */
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

        $attrString = implode(",\n    ", $attrParts);

        $traitAliases = self::registerTraitImports($traits, $imports, $usedAliases);
        $traitLines = array_map(
            static fn ($alias) => '    use ' . $alias . ';',
            array_filter($traitAliases)
        );
        $traitBlock = empty($traitLines) ? '' : "\n" . implode("\n", $traitLines) . "\n";

        // Check if base class implements interfaces - if so, don't duplicate them in wrapper
        $baseClass = $target['class'];
        $baseInterfaces = [];
        try {
            $baseReflection = new ReflectionClass($baseClass);
            $baseInterfaces = $baseReflection->getInterfaceNames();
        } catch (\Throwable $e) {
            // Ignore if base class can't be reflected
        }

        $implements = [];
        foreach ($target['interfaces'] ?? [] as $interfaceFqn) {
            // Only add interface if base class doesn't already implement it
            if (!in_array($interfaceFqn, $baseInterfaces, true)) {
                $implements[] = self::registerImport($interfaceFqn, $imports, $usedAliases);
            }
        }
        $implementsString = empty($implements) ? '' : ' implements ' . implode(', ', $implements);

        // Wrapper extends base class to inherit all methods
        $extendsString = "extends {$baseAlias}";

        $namespace = 'Syntexa\\Modules\\' . ($target['module']['studly'] ?? 'Project') . '\\Output';
        $className = $target['short'];

        $comment = <<<PHP
/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa response:generate {$className}
 */

PHP;

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

use Syntexa\Core\Attributes\AsResponse;
{$useBlock}
{$comment}
#[AsResponse(
    {$attrString}
)]
class {$className} {$extendsString}{$implementsString}
{
{$traitBlock}}

PHP;

        return $header . $body;
    }

    private static function registerImport(string $fqn, array &$imports, array &$used, ?string $preferredAlias = null): string
    {
        $fqn = ltrim($fqn, '\\');
        $hasNamespace = str_contains($fqn, '\\');
        $pos = strrpos($fqn, '\\');
        $short = $pos === false ? $fqn : substr($fqn, $pos + 1);
        $baseAlias = $preferredAlias ?: self::buildVendorAlias($fqn);
        if ($baseAlias === '') {
            $baseAlias = $short ?: 'Alias';
        }
        $alias = $baseAlias;
        $counter = 2;
        while (isset($used[$alias]) && $used[$alias] !== $fqn) {
            $alias = $baseAlias . $counter;
            $counter++;
        }
        if ($hasNamespace && !isset($used[$alias])) {
            $imports[] = [
                'fqn' => $fqn,
                'short' => $short,
                'alias' => $alias,
            ];
        }
        $used[$alias] = $fqn;
        return $alias;
    }

    private static function registerTraitImports(array $traits, array &$imports, array &$used): array
    {
        $aliases = [];
        $seen = [];
        foreach ($traits as $trait) {
            $key = ltrim($trait, '\\');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $aliases[] = self::registerImport($trait, $imports, $used);
        }
        return $aliases;
    }

    private static function buildVendorAlias(string $fqn, string $suffix = ''): string
    {
        $parts = explode('\\', ltrim($fqn, '\\'));
        $vendor = $parts[0] ?? 'Vendor';
        $short = end($parts) ?: 'Class';
        $vendor = preg_replace('/[^A-Za-z0-9]/', '', $vendor);
        if ($vendor === '') {
            $vendor = 'Vendor';
        }
        return $vendor . $short . $suffix;
    }

    private static function exportClassReference(string $class): string
    {
        return '\\' . ltrim($class, '\\');
    }

    private static function uniqueImports(array $imports): array
    {
        $seen = [];
        $result = [];
        foreach ($imports as $import) {
            $key = $import['fqn'] . ' as ' . $import['alias'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $import;
        }
        return $result;
    }

    private static function exportValue(mixed $value): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }
            $items = [];
            foreach ($value as $key => $val) {
                if (is_int($key)) {
                    $items[] = self::exportValue($val);
                } else {
                    $items[] = self::exportValue($key) . ' => ' . self::exportValue($val);
                }
            }

            return '[' . implode(', ', $items) . ']';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }

        if ($value === null) {
            return 'null';
        }

        return (string)$value;
    }
}

