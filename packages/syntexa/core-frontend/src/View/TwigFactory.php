<?php

declare(strict_types=1);

namespace Syntexa\Frontend\View;

use Syntexa\Core\ModuleRegistry;
use Syntexa\Core\Environment;
use Syntexa\Frontend\Layout\LayoutSlotRegistry;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class TwigFactory
{
    private static ?TwigEnvironment $twig = null;

    public static function get(): TwigEnvironment
    {
        if (self::$twig instanceof TwigEnvironment) {
            return self::$twig;
        }

        $loader = new FilesystemLoader();

        $modules = ModuleRegistry::getModules();
        $env = Environment::create();
        $activeTheme = $env->get('THEME', '');

        // Register themes first (override), then regular modules
        $themes = array_filter($modules, fn($m) => ($m['composerType'] ?? '') === 'syntexa-theme');
        $others = array_filter($modules, fn($m) => ($m['composerType'] ?? '') !== 'syntexa-theme');

        // If active theme specified, keep only matching themes (by alias or name)
        if ($activeTheme !== '') {
            $themes = array_filter($themes, function($m) use ($activeTheme) {
                $aliases = $m['aliases'] ?? [];
                return in_array($activeTheme, $aliases, true) || ($m['name'] ?? '') === $activeTheme;
            });
        }

        $ordered = array_merge($themes, $others);

        foreach ($ordered as $module) {
            $paths = $module['templatePaths'] ?? [];
            $aliases = $module['aliases'] ?? [$module['name']];
            foreach ($paths as $p) {
                if (!is_dir($p)) { continue; }
                foreach ($aliases as $alias) {
                    $loader->addPath($p, (string)$alias);
                }
            }
        }

        foreach (self::discoverProjectLayoutPaths() as $module => $path) {
            $loader->addPath($path, self::layoutAlias($module));
        }

        $cacheDir = self::getCacheDir();
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        self::$twig = new TwigEnvironment($loader, [
            'cache' => $cacheDir,
            'auto_reload' => true,
            'strict_variables' => false,
        ]);

        self::registerFunctions();

        return self::$twig;
    }

    private static function getCacheDir(): string
    {
        $root = dirname(__DIR__, 5);
        return $root . '/var/cache/twig';
    }

    private static function discoverProjectLayoutPaths(): array
    {
        $root = dirname(__DIR__, 5) . '/src/modules';
        if (!is_dir($root)) {
            return [];
        }

        $paths = [];
        $modules = glob($root . '/*/Layout', GLOB_ONLYDIR) ?: [];
        foreach ($modules as $layoutDir) {
            $module = basename(dirname($layoutDir));
            $paths[$module] = $layoutDir;
        }

        return $paths;
    }

    private static function layoutAlias(string $module): string
    {
        return 'project-layouts-' . $module;
    }

    private static function registerFunctions(): void
    {
        if (!(self::$twig instanceof TwigEnvironment)) {
            return;
        }

        if (class_exists(LayoutSlotRegistry::class)) {
            self::$twig->addFunction(new TwigFunction(
                'layout_slot',
                /**
                 * @param array<string, mixed> $context
                 */
                function (array $context, string $slot, array $extraContext = []): string {
                    $handle = $context['layout_handle'] ?? null;
                    if (!$handle) {
                        return '';
                    }

                    return LayoutSlotRegistry::render($handle, $slot, $context, $extraContext);
                },
                ['needs_context' => true, 'is_safe' => ['html']]
            ));
        }
    }
}


