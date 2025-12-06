<?php

declare(strict_types=1);

namespace Syntexa\Orm\CodeGen;

use ReflectionClass;
use Syntexa\Core\IntelligentAutoloader;
use Syntexa\Core\ModuleRegistry;
use Syntexa\Orm\Attributes\AsEntity;
use Syntexa\Orm\Attributes\AsEntityPart;
use Syntexa\Orm\Metadata\EntityMetadata;
use Syntexa\Orm\Metadata\EntityMetadataFactory;

/**
 * Build infrastructure-level entity representations that show
 * the owning entity and every module-provided trait extension.
 *
 * Existing files in src/infrastructure/database are treated as user-owned.
 * We only append new traits/imports and surface metadata changes without
 * rewriting developers' manual additions.
 */
class InfrastructureEntityGenerator
{
    private const OUTPUT_SUBDIR = 'infrastructure/database';
    private const COLUMN_METADATA_TAG = '@syntexa-columns';

    private static bool $bootstrapped = false;
    /** @var array<class-string, array{class: string, short: string, attr: AsEntity, file: string, module: array}> */
    private static array $cachedDefinitions = [];

    public static function generateAll(): int
    {
        $entities = self::bootstrapDefinitions();
        $total = 0;

        foreach ($entities as $target) {
            self::generateFile($target);
            $total++;
        }

        return $total;
    }

    public static function generate(string $identifier): void
    {
        $entities = self::bootstrapDefinitions();
        $target = self::resolveTarget($entities, $identifier);

        if ($target === null) {
            throw new \RuntimeException("Entity '{$identifier}' not found. Use a fully-qualified class name or unique short name.");
        }

        self::generateFile($target);
    }

    private static function bootstrapDefinitions(): array
    {
        if (!self::$bootstrapped) {
            IntelligentAutoloader::initialize();
            ModuleRegistry::initialize();
            self::$cachedDefinitions = self::collectEntityDefinitions();
            self::$bootstrapped = true;
        }

        return self::$cachedDefinitions;
    }

    private static function collectEntityDefinitions(): array
    {
        $definitions = [];
        $classes = IntelligentAutoloader::findClassesWithAttribute(AsEntity::class);

        foreach ($classes as $className) {
            $reflection = new ReflectionClass($className);
            $attr = $reflection->getAttributes(AsEntity::class)[0]->newInstance();
            $file = $reflection->getFileName() ?: '';

            $definitions[$className] = [
                'class' => $className,
                'short' => $reflection->getShortName(),
                'attr' => $attr,
                'file' => $file,
                'module' => self::detectModule($file),
            ];
        }

        return $definitions;
    }

    private static function generateFile(array $target): void
    {
        $parts = self::collectEntityParts($target['class']);
        $metadata = EntityMetadataFactory::getMetadata($target['class']);
        $columnMetadata = self::buildColumnMetadata($metadata);
        $definition = self::buildDefinition($target, $parts, $columnMetadata);

        $projectRoot = self::getProjectRoot();
        $outputDir = $projectRoot . '/src/' . self::OUTPUT_SUBDIR;
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0755, true);
        }

        $filename = $outputDir . '/' . $target['short'] . '.php';

        if (!is_file($filename)) {
            file_put_contents($filename, self::renderTemplate($definition));
            echo "✅ Infrastructure file generated: {$filename}\n";
            return;
        }

        $warnings = self::updateExistingFile($filename, $definition);
        foreach ($warnings as $warning) {
            echo $warning . "\n";
        }
        echo "🔄 Updated {$filename}\n";
    }

    /**
     * @return array<int, array{trait: string, short: string, module: array}>
     */
    private static function collectEntityParts(string $baseClass): array
    {
        $parts = [];
        $traits = IntelligentAutoloader::findClassesWithAttribute(AsEntityPart::class);

        foreach ($traits as $traitClass) {
            $reflection = new ReflectionClass($traitClass);
            $attr = $reflection->getAttributes(AsEntityPart::class)[0]->newInstance();
            $resolvedBase = self::resolveClassReference($attr->base, $traitClass);

            if ($resolvedBase === $baseClass) {
                $parts[] = [
                    'trait' => $traitClass,
                    'short' => $reflection->getShortName(),
                    'module' => self::detectModule($reflection->getFileName() ?: ''),
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

        $contextReflection = new ReflectionClass($contextClass);
        $contextNamespace = $contextReflection->getNamespaceName();
        $resolved = $contextNamespace . '\\' . $reference;

        if (class_exists($resolved)) {
            return $resolved;
        }

        if (class_exists($reference)) {
            return $reference;
        }

        return $reference;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<int, array{trait: string, short: string, module: array}> $parts
     * @param array<string, array> $columnMetadata
     */
    private static function buildDefinition(array $target, array $parts, array $columnMetadata): array
    {
        $imports = [];
        $usedAliases = [];

        $baseFqn = '\\' . ltrim($target['class'], '\\');
        $baseAlias = self::registerImport(
            $baseFqn,
            $imports,
            $usedAliases,
            self::buildVendorAlias($target['class'], 'Base')
        );

        $traitDefinitions = self::registerTraitDefinitions($parts, $imports, $usedAliases);

        return [
            'target' => $target,
            'base' => [
                'fqn' => $baseFqn,
                'alias' => $baseAlias,
            ],
            'imports' => self::uniqueImports($imports),
            'traits' => $traitDefinitions,
            'docBlock' => self::buildDocBlock($target, $parts, $columnMetadata),
            'columns' => $columnMetadata,
        ];
    }

    private static function renderTemplate(array $definition): string
    {
        $namespace = 'Syntexa\\Infrastructure\\Database';
        $className = $definition['target']['short'];

        $useLines = array_map(
            static fn (array $import) => self::formatImportLine($import),
            $definition['imports']
        );
        $useBlock = empty($useLines) ? '' : implode("\n", $useLines) . "\n\n";

        $traitLines = array_map(
            static fn (array $trait) => '    use ' . $trait['alias'] . ';',
            $definition['traits']
        );
        $traitBlock = empty($traitLines) ? '' : "\n" . implode("\n", $traitLines) . "\n";

        $useSection = $useBlock;

        return <<<PHP
<?php

declare(strict_types=1);


namespace {$namespace};

{$useSection}{$definition['docBlock']}

class {$className} extends {$definition['base']['alias']}
{
{$traitBlock}}

PHP;
    }

    private static function buildDocBlock(array $target, array $parts, array $columns): string
    {
        /** @var AsEntity $attr */
        $attr = $target['attr'];
        $lines = [
            '/**',
            ' * Infrastructure aggregate for ' . $target['class'],
        ];

        $ownerModule = $target['module']['vendor'] . '/' . $target['module']['name'];
        if ($ownerModule !== 'Project/project') {
            $lines[] = ' * Base module: ' . $ownerModule;
        }

        if ($attr->table !== null) {
            $lines[] = ' * Table: ' . $attr->table;
        }

        if (!empty($parts)) {
            $lines[] = ' *';
            $lines[] = ' * Extensions:';
            foreach ($parts as $part) {
                $module = $part['module']['vendor'] . '/' . $part['module']['name'];
                $lines[] = ' *  - ' . $module . ': ' . $part['trait'];
            }
        } else {
            $lines[] = ' *';
            $lines[] = ' * Extensions: none detected';
        }

        $columnJson = json_encode($columns, JSON_UNESCAPED_SLASHES);
        $lines[] = ' *';
        $lines[] = ' * ' . self::COLUMN_METADATA_TAG . ' ' . $columnJson;
        $lines[] = ' */';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function buildColumnMetadata(EntityMetadata $metadata): array
    {
        $columns = [];

        foreach ($metadata->columns as $property => $column) {
            $columns[$property] = [
                'entity' => $metadata->className,
                'column' => $column->columnName,
                'type' => $column->type,
                'nullable' => $column->nullable,
                'unique' => $column->unique,
                'length' => $column->length,
                'default' => $column->default,
                'timestamp' => $column->timestampType,
            ];
        }

        return $columns;
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
            ];
        }

        return ['vendor' => 'Project', 'name' => 'project'];
    }

    private static function updateExistingFile(string $file, array $definition): array
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new \RuntimeException("Unable to read {$file}");
        }

        $warnings = [];
        $existingColumns = self::extractColumnMetadata($contents, $definition['target']['short']);
        $warnings = array_merge(
            $warnings,
            self::compareColumns($existingColumns, $definition['columns'], $definition['target']['class'])
        );

        $contents = self::replaceDocBlock($contents, $definition['target']['short'], $definition['docBlock']);
        $contents = self::syncImports($contents, $definition);
        $contents = self::syncExtends($contents, $definition);
        $contents = self::syncTraitUses($contents, $definition);

        file_put_contents($file, $contents);

        return $warnings;
    }

    private static function extractColumnMetadata(string $contents, string $className): array
    {
        if (!preg_match(
            '/\/\*\*(.*?)\*\/\s*class\s+' . preg_quote($className, '/') . '/s',
            $contents,
            $docMatch
        )) {
            return [];
        }

        if (!preg_match('/' . self::COLUMN_METADATA_TAG . '\s+(\{.*\})/', $docMatch[1], $metaMatch)) {
            return [];
        }

        $decoded = json_decode($metaMatch[1], true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function compareColumns(array $previous, array $current, string $className): array
    {
        if (empty($previous)) {
            return [];
        }

        $warnings = [];
        $fieldsToCompare = ['column', 'type', 'length', 'nullable', 'unique', 'default'];

        foreach ($previous as $property => $oldMeta) {
            if (!isset($current[$property])) {
                continue;
            }

            $differences = [];
            foreach ($fieldsToCompare as $field) {
                $oldValue = $oldMeta[$field] ?? null;
                $newValue = $current[$property][$field] ?? null;
                if ($oldValue !== $newValue) {
                    $differences[] = sprintf(
                        '%s: %s -> %s',
                        $field,
                        self::stringifyValue($oldValue),
                        self::stringifyValue($newValue)
                    );
                }
            }

            if (!empty($differences)) {
                $warnings[] = sprintf(
                    "⚠️  %s::%s changed (%s)",
                    $className,
                    $property,
                    implode(', ', $differences)
                );
            }
        }

        return $warnings;
    }

    private static function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return (string) $value;
    }

    private static function replaceDocBlock(string $contents, string $className, string $docBlock): string
    {
        $pattern = '/\/\*\*(?:.|\n)*?\*\/(\s*class\s+' . preg_quote($className, '/') . ')/';
        if (preg_match($pattern, $contents)) {
            return preg_replace($pattern, $docBlock . '$1', $contents, 1) ?? $contents;
        }

        return preg_replace(
            '/(class\s+' . preg_quote($className, '/') . ')/',
            $docBlock . "\n$1",
            $contents,
            1
        ) ?? $contents;
    }

    private static function syncImports(string $contents, array &$definition): string
    {
        $pattern = '/^use\s+\\\\?([\w\\\\]+)(?:\s+as\s+(\w+))?;/m';
        preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $existing = [];
        $lastPos = null;
        foreach ($matches as $match) {
            $fqn = '\\' . ltrim($match[1][0], '\\');
            $alias = $match[2][0] ?? self::shortName($fqn);
            $existing[$fqn] = $alias;
            $lastPos = $match[0][1] + strlen($match[0][0]);
        }

        foreach ($definition['imports'] as &$import) {
            if (isset($existing[$import['fqn']])) {
                $import['alias'] = $existing[$import['fqn']];
            }
        }
        unset($import);

        foreach ($definition['imports'] as $import) {
            if ($import['fqn'] === $definition['base']['fqn']) {
                $definition['base']['alias'] = $import['alias'];
            }
            foreach ($definition['traits'] as &$trait) {
                if ($trait['fqn'] === $import['fqn']) {
                    $trait['alias'] = $import['alias'];
                }
            }
        }
        unset($trait);

        $missing = array_filter(
            $definition['imports'],
            static fn ($import) => !isset($existing[$import['fqn']])
        );

        if (empty($missing)) {
            return $contents;
        }

        $insertion = implode("\n", array_map(
            static fn ($import) => self::formatImportLine($import),
            $missing
        )) . "\n";

        if ($lastPos !== null) {
            return substr_replace($contents, "\n" . $insertion, $lastPos, 0);
        }

        if (preg_match('/namespace[^;]+;/', $contents, $nsMatch, PREG_OFFSET_CAPTURE)) {
            $pos = $nsMatch[0][1] + strlen($nsMatch[0][0]);
            return substr_replace($contents, "\n\n" . $insertion, $pos, 0);
        }

        return $insertion . "\n" . $contents;
    }

    private static function syncExtends(string $contents, array $definition): string
    {
        $className = $definition['target']['short'];
        $alias = $definition['base']['alias'];
        $pattern = '/(class\s+' . preg_quote($className, '/') . '\s+extends\s+)([^\s{]+)/';

        if (preg_match($pattern, $contents)) {
            return preg_replace_callback(
                $pattern,
                static fn (array $matches) => $matches[1] . $alias,
                $contents,
                1
            ) ?? $contents;
        }

        return preg_replace(
            '/(class\s+' . preg_quote($className, '/') . ')/',
            '$1 extends ' . $alias,
            $contents,
            1
        ) ?? $contents;
    }

    private static function syncTraitUses(string $contents, array $definition): string
    {
        preg_match_all('/^\s{4}use\s+(\w+)\s*;/m', $contents, $matches);
        $existingAliases = $matches[1] ?? [];

        $missing = [];
        foreach ($definition['traits'] as $trait) {
            if (!in_array($trait['alias'], $existingAliases, true)) {
                $missing[] = '    use ' . $trait['alias'] . ';';
            }
        }

        if (empty($missing)) {
            return $contents;
        }

        $insert = (empty($existingAliases) ? "\n" : '') . implode("\n", $missing) . "\n";
        $classEndPos = strrpos($contents, '}');

        if ($classEndPos === false) {
            return $contents . "\n" . $insert;
        }

        return substr_replace($contents, $insert, $classEndPos, 0);
    }

    private static function registerImport(string $fqn, array &$imports, array &$usedAliases, ?string $alias = null): string
    {
        $parts = explode('\\', ltrim($fqn, '\\'));
        $short = end($parts);
        $alias = $alias ?? $short;

        if (isset($usedAliases[$alias])) {
            $alias .= count($usedAliases);
        }

        $imports[$fqn] = [
            'fqn' => '\\' . ltrim($fqn, '\\'),
            'short' => $short,
            'alias' => $alias,
        ];

        $usedAliases[$alias] = true;

        return $alias;
    }

    /**
     * @return array<int, array{fqn: string, alias: string}>
     */
    private static function registerTraitDefinitions(array $parts, array &$imports, array &$usedAliases): array
    {
        $definitions = [];

        foreach ($parts as $part) {
            $trait = ltrim($part['trait'], '\\');
            $alias = self::buildVendorAlias($trait, 'Trait');
            $alias = self::registerImport($trait, $imports, $usedAliases, $alias);
            $definitions[] = [
                'fqn' => '\\' . ltrim($trait, '\\'),
                'alias' => $alias,
            ];
        }

        return $definitions;
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
            $unique[$import['fqn']] = $import;
        }
        return array_values($unique);
    }

    private static function formatImportLine(array $import): string
    {
        $aliasClause = $import['alias'] !== $import['short'] ? ' as ' . $import['alias'] : '';
        return 'use ' . $import['fqn'] . $aliasClause . ';';
    }

    private static function resolveTarget(array $entities, string $identifier): ?array
    {
        if (isset($entities[$identifier])) {
            return $entities[$identifier];
        }

        foreach ($entities as $entity) {
            if ($entity['short'] === $identifier) {
                return $entity;
            }
        }

        return null;
    }

    private static function getProjectRoot(): string
    {
        $dir = __DIR__;
        while ($dir !== '/' && $dir !== '') {
            if (file_exists($dir . '/bin/syntexa')) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        return dirname(__DIR__, 4);
    }

    private static function shortName(string $fqn): string
    {
        $parts = explode('\\', ltrim($fqn, '\\'));
        return end($parts) ?: $fqn;
    }
}

