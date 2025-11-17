<?php

declare(strict_types=1);

namespace Syntexa\Frontend\Layout;

use Syntexa\Core\ModuleRegistry;

class LayoutLoader
{
    public static function loadHandle(string $handle): ?\SimpleXMLElement
    {
        $files = self::findLayoutFiles($handle);
        if (empty($files)) {
            error_log("No layout files found for handle '{$handle}'");
            return null;
        }

        // Start with first file as base
        $baseXml = simplexml_load_file($files[0]['path']);
        if (!$baseXml) {
            error_log("Failed to load XML from: {$files[0]['path']}");
            return null;
        }

        // Merge subsequent files in order
        for ($i = 1; $i < count($files); $i++) {
            $next = simplexml_load_file($files[$i]['path']);
            if ($next) {
                $baseXml = self::mergeContainers($baseXml, $next);
            }
        }

        // Support extends="..." at the final stage (child can extend a base)
        $extends = (string)($baseXml['extends'] ?? '');
        if ($extends !== '') {
            $parent = self::loadHandle($extends);
            if ($parent) {
                // When extending, parent is the base, child merges into it
                // This ensures parent's template and structure are preserved
                $baseXml = self::mergeContainers($parent, $baseXml);
            }
        }

        // Apply layout overrides from AsLayoutOverride attributes (from src/)
        self::applyLayoutOverrides($baseXml, $handle);

        return $baseXml;
    }

    private static function findLayoutFiles(string $handle): array
    {
        $found = [];
        $modules = ModuleRegistry::getModules();
        if (empty($modules)) {
            error_log("No modules registered in ModuleRegistry");
        }
        foreach ($modules as $module) {
            $dir = $module['path'] . '/src/Application/View/templates/layout';
            $file = $dir . '/' . $handle . '.xml';
            if (is_file($file)) {
                $priority = 10; // default for modules
                $composerType = $module['composerType'] ?? 'syntexa-module';
                if (($module['name'] ?? '') === 'module-core-frontend') { $priority = 0; }
                if ($composerType === 'syntexa-theme') { $priority = 20; }
                $found[] = [
                    'path' => $file,
                    'priority' => $priority,
                ];
            }
        }
        // Sort by priority (low first -> base), keep stable order within same priority
        usort($found, fn($a, $b) => $a['priority'] <=> $b['priority']);
        if (empty($found)) {
            error_log("No layout files found for handle '{$handle}'. Searched in " . count($modules) . " modules.");
        }
        return $found;
    }

    private static function mergeContainers(\SimpleXMLElement $base, \SimpleXMLElement $child): \SimpleXMLElement
    {
        // If both are root containers, preserve base's template and other attributes
        // but allow child to override if explicitly set
        if ($base->getName() === 'container' && $child->getName() === 'container') {
            // Store base template before merging (in case child doesn't have one)
            $baseTemplate = (string)($base['template'] ?? '');
            
            // Copy child attributes to base only if they don't exist in base (except 'extends')
            foreach ($child->attributes() as $key => $value) {
                $keyStr = (string)$key;
                if ($keyStr !== 'extends' && !isset($base[$keyStr])) {
                    $base->addAttribute($keyStr, (string)$value);
                }
            }
            
            // If base had a template and child doesn't override it, ensure it's preserved
            if ($baseTemplate !== '' && !isset($child['template'])) {
                // Template is already in base, so it's preserved
            }
        }

        // Process structural operations first: remove / move
        foreach ($child->children() as $op) {
            if ($op->getName() === 'remove' && isset($op['name'])) {
                self::removeNodeByName($base, (string)$op['name']);
            }
            if ($op->getName() === 'move' && isset($op['name']) && isset($op['into'])) {
                self::moveNode($base, (string)$op['name'], (string)$op['into'], (string)($op['before'] ?? ''), (string)($op['after'] ?? ''));
            }
        }

        // Merge child containers/blocks into base by container name
        foreach ($child->children() as $node) {
            $name = $node->getName();
            if ($name === 'container' && isset($node['name'])) {
                $targetName = (string)$node['name'];
                $target = self::findContainerByName($base, $targetName);
                if ($target) {
                    self::appendChildren($target, $node);
                } else {
                    self::appendNode($base, $node);
                }
            } elseif ($name === 'block') {
                self::appendNode($base, $node);
            }
        }
        return $base;
    }

    private static function findContainerByName(\SimpleXMLElement $root, string $name): ?\SimpleXMLElement
    {
        foreach ($root->children() as $node) {
            if ($node->getName() === 'container' && (string)($node['name'] ?? '') === $name) {
                return $node;
            }
        }
        return null;
    }

    /**
     * Find a block by its name attribute within a container or root.
     */
    private static function findBlockByName(\SimpleXMLElement $root, string $name): ?\SimpleXMLElement
    {
        foreach ($root->children() as $node) {
            if ($node->getName() === 'block' && (string)($node['name'] ?? '') === $name) {
                return $node;
            }
            // Recurse into containers
            if ($node->getName() === 'container') {
                $found = self::findBlockByName($node, $name);
                if ($found) {
                    return $found;
                }
            }
        }
        return null;
    }

    private static function appendChildren(\SimpleXMLElement $target, \SimpleXMLElement $source): void
    {
        foreach ($source->children() as $child) {
            self::appendNode($target, $child);
        }
    }

    private static function appendNode(\SimpleXMLElement $target, \SimpleXMLElement $node): void
    {
        $new = $target->addChild($node->getName());
        foreach ($node->attributes() as $k => $v) {
            $new->addAttribute((string)$k, (string)$v);
        }
        foreach ($node->children() as $c) {
            self::appendNode($new, $c);
        }
        // text content for <arg>
        $text = (string)$node;
        if ($text !== '' && count($node->children()) === 0) {
            $new[0] = $text;
        }
    }

    private static function removeNodeByName(\SimpleXMLElement $root, string $name): void
    {
        $idx = 0;
        foreach ($root->children() as $node) {
            $isMatch = ((string)($node['name'] ?? '') === $name)
                || ((string)($node['template'] ?? '') === $name);
            if ($isMatch) {
                unset($root->children()[$idx]);
                return;
            }
            $idx++;
        }
        // Recurse containers
        foreach ($root->children() as $node) {
            if ($node->getName() === 'container') {
                self::removeNodeByName($node, $name);
            }
        }
    }

    private static function moveNode(\SimpleXMLElement $root, string $name, string $into, string $before = '', string $after = ''): void
    {
        $nodeRef = self::detachNodeByName($root, $name);
        if (!$nodeRef) { return; }
        $dest = self::findContainerByName($root, $into);
        if (!$dest) { $dest = $root; }
        // Append at end for now; positioning (before/after) could be handled later
        self::appendNode($dest, $nodeRef);
    }

    private static function detachNodeByName(\SimpleXMLElement $root, string $name): ?\SimpleXMLElement
    {
        $idx = 0;
        foreach ($root->children() as $node) {
            $isMatch = ((string)($node['name'] ?? '') === $name)
                || ((string)($node['template'] ?? '') === $name);
            if ($isMatch) {
                // Clone node before unsetting
                $cloned = new \SimpleXMLElement($node->asXML() ?: '<block />');
                unset($root->children()[$idx]);
                return $cloned;
            }
            $idx++;
        }
        foreach ($root->children() as $node) {
            if ($node->getName() === 'container') {
                $found = self::detachNodeByName($node, $name);
                if ($found) { return $found; }
            }
        }
        return null;
    }

    /**
     * Apply layout overrides registered via AsLayoutOverride attributes.
     * Operations are applied in order: remove, move, add.
     */
    private static function applyLayoutOverrides(\SimpleXMLElement $xml, string $handle): void
    {
        if (!class_exists('Syntexa\\Frontend\\Layout\\LayoutOverrideRegistry')) {
            return;
        }
        
        $overrides = \Syntexa\Frontend\Layout\LayoutOverrideRegistry::getOverrides($handle);
        if (empty($overrides)) {
            return;
        }

        foreach ($overrides as $override) {
            foreach ($override['operations'] as $op) {
                $type = $op['type'] ?? '';
                
                if ($type === 'remove' && isset($op['name'])) {
                    self::removeNodeByName($xml, (string)$op['name']);
                } elseif ($type === 'move' && isset($op['name']) && isset($op['into'])) {
                    self::moveNode(
                        $xml,
                        (string)$op['name'],
                        (string)$op['into'],
                        (string)($op['before'] ?? ''),
                        (string)($op['after'] ?? '')
                    );
                } elseif ($type === 'add' && isset($op['block']) && isset($op['into'])) {
                    self::addBlockFromArray($xml, $op['block'], (string)$op['into'], (string)($op['before'] ?? ''), (string)($op['after'] ?? ''));
                }
            }
        }
    }

    /**
     * Add a block to the layout from an array definition.
     * 
     * @param array<string, mixed> $blockDef Block definition with 'name', 'template', 'args', etc.
     */
    private static function addBlockFromArray(\SimpleXMLElement $root, array $blockDef, string $into, string $before = '', string $after = ''): void
    {
        $container = self::findContainerByName($root, $into);
        if (!$container) {
            // If container doesn't exist, create it
            $container = $root->addChild('container');
            $container->addAttribute('name', $into);
        }

        // Create the block XML structure first
        $blockXml = '<block';
        if (isset($blockDef['name'])) {
            $blockXml .= ' name="' . htmlspecialchars((string)$blockDef['name'], ENT_XML1) . '"';
        }
        if (isset($blockDef['template'])) {
            $blockXml .= ' template="' . htmlspecialchars((string)$blockDef['template'], ENT_XML1) . '"';
        }
        if (isset($blockDef['handle'])) {
            $blockXml .= ' handle="' . htmlspecialchars((string)$blockDef['handle'], ENT_XML1) . '"';
        }
        $blockXml .= '>';
        
        // Add arguments
        if (isset($blockDef['args']) && is_array($blockDef['args'])) {
            foreach ($blockDef['args'] as $argName => $argValue) {
                $blockXml .= '<arg name="' . htmlspecialchars((string)$argName, ENT_XML1) . '">' 
                    . htmlspecialchars((string)$argValue, ENT_XML1) . '</arg>';
            }
        }
        $blockXml .= '</block>';
        
        $newBlock = new \SimpleXMLElement($blockXml);
        
        // Handle positioning: find reference block if before/after specified
        if ($before !== '' || $after !== '') {
            $refName = $before !== '' ? $before : $after;
            $refBlock = self::findBlockByName($container, $refName);
            
            if ($refBlock) {
                // Insert before or after the reference block
                // SimpleXML doesn't support direct insertion, so we'll append for now
                // Proper positioning would require rebuilding the XML tree
                self::appendNode($container, $newBlock);
            } else {
                // Reference block not found, append at end
                self::appendNode($container, $newBlock);
            }
        } else {
            // No positioning specified, append at end
            self::appendNode($container, $newBlock);
        }
    }
}


