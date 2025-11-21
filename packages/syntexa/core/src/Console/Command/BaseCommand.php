<?php

declare(strict_types=1);

namespace Syntexa\Core\Console\Command;

use Symfony\Component\Console\Command\Command;

/**
 * Base command with helper methods
 */
abstract class BaseCommand extends Command
{
    /**
     * Get project root directory (where composer.json and src/modules are located)
     */
    protected function getProjectRoot(): string
    {
        $dir = __DIR__;
        while ($dir !== '/' && $dir !== '') {
            if (file_exists($dir . '/composer.json')) {
                // Check if this is the actual project root (has src/modules)
                if (is_dir($dir . '/src/modules')) {
                    return $dir;
                }
            }
            $dir = dirname($dir);
        }
        
        // Fallback: go up 4 levels from Console/Command
        return dirname(__DIR__, 4);
    }
}

