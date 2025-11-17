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
                if ($meta->of === $baseClass) {
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
        $traits = $matches[1] ?? [];

        return array_map(
            static fn ($trait) => '\\' . ltrim($trait, '\\'),
            $traits
        );
    }

    private static function writeWrapper(array $target, array $parts): void
    {
        $projectRoot = dirname(__DIR__, 5);
        $moduleStudly = $target['module']['studly'] ?? 'Project';
        $outputDir = $projectRoot . '/src/modules/' . $moduleStudly . '/Response';
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
        $attrParts = [];
        if ($attr->handle !== null) {
            $attrParts[] = "handle: '" . addslashes(EnvValueResolver::resolve($attr->handle)) . "'";
        }
        if ($attr->format !== null) {
            $attrParts[] = 'format: \\' . ltrim($attr->format::class, '\\') . '::' . $attr->format->name;
        }
        if ($attr->renderer !== null) {
            $attrParts[] = "renderer: '" . addslashes(EnvValueResolver::resolve($attr->renderer)) . "'";
        }
        if (!empty($attr->context)) {
            $attrParts[] = 'context: ' . self::exportValue(EnvValueResolver::resolve($attr->context));
        }

        $attrString = implode(",\n    ", $attrParts);
        if ($attrString === '') {
            $attrString = "// no AsResponse metadata";
        }

        $traitLines = array_map(
            static fn ($trait) => '    use ' . $trait . ';',
            array_filter($traits)
        );
        $traitBlock = empty($traitLines) ? '' : "\n" . implode("\n", $traitLines) . "\n";

        $namespace = 'Syntexa\\Modules\\' . ($target['module']['studly'] ?? 'Project') . '\\Response';
        $baseClass = '\\' . ltrim($target['class'], '\\');
        $className = $target['short'];

        $header = <<<'PHP'
<?php

declare(strict_types=1);

/**
 * AUTO-GENERATED FILE.
 * Regenerate via: bin/syntexa response:generate %s
 */

PHP;

        $header = sprintf($header, $className);

        $body = <<<PHP
namespace {$namespace};

use Syntexa\Core\Attributes\AsResponse;

#[AsResponse(
    {$attrString}
)]
class {$className} extends {$baseClass}
{
{$traitBlock}}

PHP;

        return $header . $body;
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

