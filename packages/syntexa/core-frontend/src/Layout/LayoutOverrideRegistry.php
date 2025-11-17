<?php

declare(strict_types=1);

namespace Syntexa\Frontend\Layout;

/**
 * Registry for layout overrides discovered via AsLayoutOverride attributes.
 * Stores operations (move, remove, add) that should be applied to specific layout handles.
 */
class LayoutOverrideRegistry
{
    /** @var array<string, array<int, array{operations: array, priority: int}>> */
    private static array $byHandle = [];
    private static bool $initialized = false;

    public static function reset(): void
    {
        self::$byHandle = [];
        self::$initialized = false;
    }

    /**
     * Register layout override operations for a specific handle.
     * 
     * @param string $handle Layout handle name
     * @param array<int, array{type: 'move'|'remove'|'add', name?: string, into?: string, before?: string, after?: string, block?: array<string, mixed>}> $operations
     * @param int $priority Higher priority overrides are applied later
     */
    public static function register(string $handle, array $operations, int $priority = 100): void
    {
        if (!isset(self::$byHandle[$handle])) {
            self::$byHandle[$handle] = [];
        }
        self::$byHandle[$handle][] = [
            'operations' => $operations,
            'priority' => $priority
        ];
        // Sort by priority (lower first, so higher priority applies last)
        usort(self::$byHandle[$handle], fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * Get all override operations for a specific handle, sorted by priority.
     * 
     * @param string $handle Layout handle name
     * @return array<int, array{operations: array, priority: int}>
     */
    public static function getOverrides(string $handle): array
    {
        return self::$byHandle[$handle] ?? [];
    }

    /**
     * Check if any overrides exist for a handle.
     */
    public static function hasOverrides(string $handle): bool
    {
        return !empty(self::$byHandle[$handle]);
    }
}

