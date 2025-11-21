<?php

declare(strict_types=1);

namespace Syntexa\Frontend\Layout;

use Syntexa\Frontend\View\TwigFactory;

class LayoutRenderer
{
    public static function renderHandle(string $handle, array $context = []): string
    {
        $layout = LayoutLoader::loadHandle($handle);
        if ($layout === null) {
            return '<!doctype html><html><head><meta charset="utf-8"><title>'
                . htmlspecialchars($context['title'] ?? 'Layout missing')
                . '</title></head><body><main><p>Layout handle \''
                . htmlspecialchars($handle)
                . '\' is not activated. Run bin/syntexa layout:generate '
                . htmlspecialchars($handle)
                . '</p></main></body></html>';
        }
        try {
            return TwigFactory::get()->render(
                $layout['template'],
                array_merge(
                    [
                        'layout_handle' => $handle,
                        'layout_module' => $layout['module'],
                    ],
                    $context
                )
            );
        } catch (\Throwable $e) {
            error_log("Error rendering layout '{$handle}': " . $e->getMessage());
            return '<!doctype html><html><head><meta charset="utf-8"><title>'
                . htmlspecialchars($handle)
                . '</title></head><body><main><pre>'
                . htmlspecialchars($e->getMessage())
                . '</pre></main></body></html>';
        }
    }
}


