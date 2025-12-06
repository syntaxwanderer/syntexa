<?php

declare(strict_types=1);

namespace Syntexa\Orm\Migration;

/**
 * Finds migration classes in the project
 */
class MigrationFinder
{
    /**
     * Find all migration classes
     * 
     * Searches in:
     * - src/infrastructure/migrations/ (primary location)
     * 
     * @param string $projectRoot Project root directory
     * @return array<string> Array of fully qualified class names
     */
    public static function findMigrations(string $projectRoot): array
    {
        $migrations = [];
        
        // Search in src/infrastructure/migrations (primary location)
        $migrationDir = $projectRoot . '/src/infrastructure/migrations';
        if (is_dir($migrationDir)) {
            $migrationFiles = glob($migrationDir . '/*.php');
            foreach ($migrationFiles as $file) {
                $className = self::getClassNameFromFile($file);
                if ($className && is_subclass_of($className, AbstractMigration::class)) {
                    $migrations[] = $className;
                }
            }
        }
        
        // Sort by version (extracted from class name)
        usort($migrations, function ($a, $b) {
            $versionA = MigrationExecutor::getVersionFromClassName($a);
            $versionB = MigrationExecutor::getVersionFromClassName($b);
            return strcmp($versionA, $versionB);
        });
        
        return $migrations;
    }

    /**
     * Get class name from file
     * 
     * Requires the file to load the class, then uses reflection
     * to get the fully qualified class name.
     * 
     * @param string $filePath File path
     * @return string|null Fully qualified class name or null
     */
    private static function getClassNameFromFile(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }

        // Require the file to load the class
        try {
            require_once $filePath;
        } catch (\Throwable $e) {
            return null;
        }

        // Extract namespace and class name from file content
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Extract namespace
        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        // Extract class name
        $className = null;
        if (preg_match('/class\s+(\w+)\s+extends\s+AbstractMigration/', $content, $matches)) {
            $className = trim($matches[1]);
        }

        if ($className === null) {
            return null;
        }

        $fullClassName = $namespace !== null 
            ? $namespace . '\\' . $className
            : $className;

        // Verify class exists and extends AbstractMigration
        if (!class_exists($fullClassName)) {
            return null;
        }

        return $fullClassName;
    }
}

