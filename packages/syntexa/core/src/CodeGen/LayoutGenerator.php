<?php

declare(strict_types=1);

namespace Syntexa\Core\CodeGen;

use Syntexa\Core\ModuleRegistry;

class LayoutGenerator
{
    private static bool $bootstrapped = false;
    /** @var array<int, array<string, mixed>> */
    private static array $layouts = [];

    /**
     * Generate (or refresh) a layout copy for the given identifier.
     *
     * Identifier formats:
     *  - handle (e.g. "login") if unique across modules
     *  - Module/handle (e.g. "UserFrontend/login")
     *  - module-handle/handle (e.g. "module-user-frontend/login")
     */
    public static function generate(string $identifier): void
    {
        $layouts = self::bootstrap();
        $target = self::resolveTarget($layouts, $identifier);
        if ($target === null) {
            throw new \RuntimeException("Layout '{$identifier}' not found. Use 'Module/handle' if the handle is duplicated.");
        }

        self::writeLayout($target);
    }

    /**
     * Generate layout copies for every discovered module layout.
     */
    public static function generateAll(): void
    {
        $layouts = self::bootstrap();
        $generated = 0;

        foreach ($layouts as $layout) {
            if (self::writeLayout($layout, true)) {
                $generated++;
            }
        }

        if ($generated === 0) {
            echo "ℹ️  No new layouts to copy – everything already exists in src/.\n";
        } else {
            echo "✨ Copied {$generated} layout(s) into src/modules/*/Layout.\n";
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function bootstrap(): array
    {
        if (!self::$bootstrapped) {
            ModuleRegistry::initialize();
            self::$layouts = self::collectLayouts();
            self::$bootstrapped = true;
        }

        return self::$layouts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function collectLayouts(): array
    {
        $result = [];
        $projectRoot = self::getProjectRoot();

        foreach (ModuleRegistry::getModules() as $module) {
            $modulePath = $module['path'] ?? null;
            if ($modulePath === null) {
                continue;
            }
            $layoutDir = rtrim($modulePath, '/') . '/src/Application/View/templates/layout';
            if (!is_dir($layoutDir)) {
                continue;
            }

            $files = glob($layoutDir . '/*.html.twig') ?: [];
            if (empty($files)) {
                continue;
            }

            $moduleName = $module['name'] ?? 'module';
            $studly = self::slugToStudly($moduleName);

            foreach ($files as $file) {
                $handle = basename($file, '.html.twig');
                $result[] = [
                    'id' => $studly . '/' . $handle,
                    'handle' => $handle,
                    'module' => $moduleName,
                    'moduleStudly' => $studly,
                    'source' => $file,
                    'destination' => $projectRoot . '/src/modules/' . $studly . '/Layout/' . basename($file),
                ];
            }
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $layouts
     */
    private static function resolveTarget(array $layouts, string $identifier): ?array
    {
        $normalized = strtolower($identifier);
        foreach ($layouts as $layout) {
            if (strtolower($layout['id']) === $normalized) {
                return $layout;
            }
        }

        $matches = array_values(array_filter(
            $layouts,
            fn ($layout) => strtolower($layout['handle']) === $normalized
                || strtolower($layout['module'] . '/' . $layout['handle']) === $normalized
        ));

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            $list = implode("\n - ", array_map(fn ($layout) => $layout['id'], $matches));
            throw new \RuntimeException("Ambiguous layout identifier '{$identifier}'. Matches:\n - {$list}");
        }

        return null;
    }

    /**
     * @param array<string, mixed> $layout
     */
    private static function writeLayout(array $layout, bool $silentSkip = false): bool
    {
        $destination = $layout['destination'];
        $dir = dirname($destination);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (is_file($destination)) {
            if (!$silentSkip) {
                echo "↩️  Layout {$layout['id']} already exists. Skipping.\n";
            }
            return false;
        }

        $contents = file_get_contents($layout['source']);
        if ($contents === false) {
            throw new \RuntimeException("Failed to read layout source: {$layout['source']}");
        }

        $comment = "{# AUTO-GENERATED FROM {$layout['module']}/{$layout['handle']}. Edit freely in src/. #}\n";
        $payload = $comment . $contents;

        if (file_put_contents($destination, $payload) === false) {
            throw new \RuntimeException("Unable to write layout copy to {$destination}");
        }

        echo "✅ Layout {$layout['id']} copied to {$destination}\n";
        return true;
    }

    private static function slugToStudly(string $slug): string
    {
        $parts = preg_split('/[-_]/', $slug);
        $parts = array_map(static fn ($p) => ucfirst(strtolower($p)), array_filter($parts));

        return $parts === [] ? 'Module' : implode('', $parts);
    }

    private static function getProjectRoot(): string
    {
        static $root = null;
        if ($root !== null) {
            return $root;
        }

        $dir = dirname(__DIR__, 4);
        while ($dir !== '/' && !is_file($dir . '/composer.json')) {
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        $root = $dir;
        return $root;
    }
}



