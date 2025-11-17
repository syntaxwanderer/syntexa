<?php

declare(strict_types=1);

namespace Syntexa\Frontend\Layout;

use Syntexa\Frontend\View\TwigFactory;
use Syntexa\Frontend\Block\BlockContext;
use Syntexa\Frontend\Block\RenderState;

class LayoutRenderer
{
    public static function renderHandle(string $handle, array $context = []): string
    {
        $xml = LayoutLoader::loadHandle($handle);
        if (!$xml) {
            // Fallback minimal HTML to avoid empty responses
            return '<!doctype html><html><head><meta charset="utf-8"><title>'
                . htmlspecialchars($context['title'] ?? 'Layout')
                . '</title></head><body><main></main></body></html>';
        }
        $result = self::renderNode($xml, $context);
        // If result is empty or very short, it might indicate a rendering issue
        if (trim($result) === '' || strlen(trim($result)) < 50) {
            // Debug: add debug info to HTML
            $template = (string)($xml['template'] ?? 'NO TEMPLATE');
            $childrenCount = count($xml->children());
            $structure = [];
            foreach ($xml->children() as $child) {
                $childName = $child->getName();
                $childAttr = (string)($child['name'] ?? 'no-name');
                $structure[] = "{$childName}[{$childAttr}]";
            }
            $debug = "<!-- DEBUG: Handle={$handle}, Template={$template}, Children={$childrenCount}, Structure=" . implode(', ', $structure) . " -->";
            return $debug . $result;
        }
        return $result;
    }

    private static function renderNode(\SimpleXMLElement $node, array $context): string
    {
        $name = $node->getName();
        if ($name === 'container') {
            // Collect regions by child container name; concatenate blocks into content
            $regions = [];
            $content = '';
            foreach ($node->children() as $child) {
                if ($child->getName() === 'container') {
                    $regionName = (string)($child['name'] ?? 'content');
                    $regions[$regionName] = ($regions[$regionName] ?? '') . self::renderNode($child, $context);
                } else {
                    $content .= self::renderNode($child, $context);
                }
            }
            $template = (string)($node['template'] ?? '');
            if ($template !== '') {
                return TwigFactory::get()->render((string)$template, ['content' => $content] + $regions + $context);
            }
            return $content;
        }

        if ($name === 'block') {
            $template = (string)($node['template'] ?? '');
            if ($template === '') {
                // Block without template - skip rendering
                return '';
            }
            $data = $context;
            // Collect <arg name="...">value</arg>
            foreach ($node->children() as $child) {
                if ($child->getName() === 'arg' && isset($child['name'])) {
                    $data[(string)$child['name']] = (string)$child;
                }
            }
            // Execute BlockHandlers if block class name provided
            $blockClass = (string)($node['name'] ?? '');
            if ($blockClass !== '') {
                $handlers = BlockHandlerRegistry::getHandlers($blockClass);
                if (!empty($handlers)) {
                    $state = new RenderState($data);
                    $ctx = new BlockContext(handle: (string)($node['handle'] ?? ''), args: $data, request: null);
                    foreach ($handlers as $h) {
                        $inst = new ($h['class'])();
                        if (method_exists($inst, 'handle')) {
                            $state = $inst->handle($ctx, $state);
                        }
                    }
                    $data = $state->data;
                    if (isset($data['template']) && is_string($data['template'])) {
                        $template = $data['template'];
                    }
                }
            }
            try {
                $rendered = TwigFactory::get()->render($template, $data);
                return $rendered;
            } catch (\Throwable $e) {
                // Log error but don't break rendering
                error_log("Error rendering block template '{$template}': " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
                return '<!-- Error rendering: ' . htmlspecialchars($e->getMessage()) . ' -->';
            }
        }

        // Unknown node
        return '';
    }
}


