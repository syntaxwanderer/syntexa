<?php

declare(strict_types=1);

namespace Syntexa\Frontend\Attributes;

use Attribute;

/**
 * Declares layout modifications for a specific layout handle.
 * Allows moving, removing, or adding blocks to layouts without modifying XML files.
 * 
 * This attribute should be placed in the project's src/ directory to override
 * module-defined layouts in a transparent and maintainable way.
 * 
 * Operations are applied in order after XML layout files are loaded and merged.
 * 
 * Example:
 * ```php
 * #[AsLayoutOverride(
 *     handle: 'login',
 *     operations: [
 *         ['type' => 'move', 'name' => 'helpBlock', 'into' => 'sidebar', 'after' => 'navBlock'],
 *         ['type' => 'remove', 'name' => 'oldBlock'],
 *         ['type' => 'add', 'block' => [
 *             'name' => 'MyCustomBlock',
 *             'template' => '@my-module/block/custom.html.twig',
 *             'args' => ['title' => 'Custom Title']
 *         ], 'into' => 'main']
 *     ],
 *     priority: 100
 * )]
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AsLayoutOverride
{
    /**
     * @param string $handle The layout handle to modify (e.g., 'login', 'homepage')
     * @param array<int, array{type: 'move'|'remove'|'add', name?: string, into?: string, before?: string, after?: string, block?: array<string, mixed>}> $operations Array of layout operations to apply
     * @param int $priority Higher priority overrides are applied later (default: 100)
     */
    public function __construct(
        public string $handle,
        public array $operations = [],
        public int $priority = 100
    ) {}
}

