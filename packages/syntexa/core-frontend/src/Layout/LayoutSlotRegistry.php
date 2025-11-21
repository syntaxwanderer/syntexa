<?php

declare(strict_types=1);

namespace Syntexa\Frontend\Layout;

use Syntexa\Frontend\View\TwigFactory;

class LayoutSlotRegistry
{
    /**
     * @var array<string, array<string, array<int, array{template:string, context:array, priority:int}>>>
     */
    private static array $slots = [];

    public static function register(string $handle, string $slot, string $template, array $context = [], int $priority = 0): void
    {
        $handleKey = strtolower($handle);
        $slotKey = strtolower($slot);
        self::$slots[$handleKey][$slotKey][] = [
            'template' => $template,
            'context' => $context,
            'priority' => $priority,
        ];
        usort(self::$slots[$handleKey][$slotKey], static fn ($a, $b) => $a['priority'] <=> $b['priority']);
    }

    public static function render(string $handle, string $slot, array $baseContext = [], array $inlineContext = []): string
    {
        $entries = self::$slots[strtolower($handle)][strtolower($slot)] ?? [];
        if (empty($entries)) {
            return '';
        }

        $twig = TwigFactory::get();
        $html = '';

        foreach ($entries as $entry) {
            $context = array_merge($baseContext, $entry['context'], $inlineContext);
            $html .= $twig->render($entry['template'], $context);
        }

        return $html;
    }
}

