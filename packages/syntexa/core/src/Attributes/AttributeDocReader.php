<?php

declare(strict_types=1);

namespace Syntexa\Core\Attributes;

use ReflectionAttribute;
use ReflectionClass;

/**
 * Utility class for reading documentation from attributes
 * 
 * This class helps AI assistants and tools to automatically
 * discover and read documentation files referenced by attributes.
 */
class AttributeDocReader
{
    /**
     * Get documentation path from an attribute instance
     * 
     * @param object $attribute Attribute instance
     * @return string|null Path to documentation file or null if not available
     */
    public static function getDocPath(object $attribute): ?string
    {
        if ($attribute instanceof DocumentedAttributeInterface) {
            return $attribute->getDocPath();
        }
        
        return null;
    }

    /**
     * Read documentation content from an attribute instance
     * 
     * @param object $attribute Attribute instance
     * @param string $projectRoot Project root directory
     * @return string|null Documentation content or null if not found
     */
    public static function readDoc(object $attribute, string $projectRoot): ?string
    {
        $docPath = self::getDocPath($attribute);
        if ($docPath === null) {
            return null;
        }
        
        // Handle relative paths
        if (!str_starts_with($docPath, '/')) {
            $docPath = $projectRoot . '/' . ltrim($docPath, '/');
        }
        
        if (!file_exists($docPath)) {
            return null;
        }
        
        return file_get_contents($docPath) ?: null;
    }

    /**
     * Get all documentation paths from a class's attributes
     * 
     * @param string|ReflectionClass $class Class name or ReflectionClass instance
     * @return array<string, string> Map of attribute class name => doc path
     */
    public static function getClassAttributeDocs(string|ReflectionClass $class): array
    {
        $reflection = $class instanceof ReflectionClass ? $class : new ReflectionClass($class);
        $docs = [];
        
        foreach ($reflection->getAttributes() as $attr) {
            $instance = $attr->newInstance();
            if ($instance instanceof DocumentedAttributeInterface) {
                $docs[$attr->getName()] = $instance->getDocPath();
            }
        }
        
        return $docs;
    }

    /**
     * Get all documentation paths from a property's attributes
     * 
     * @param ReflectionClass $class ReflectionClass instance
     * @param string $propertyName Property name
     * @return array<string, string> Map of attribute class name => doc path
     */
    public static function getPropertyAttributeDocs(ReflectionClass $class, string $propertyName): array
    {
        if (!$class->hasProperty($propertyName)) {
            return [];
        }
        
        $property = $class->getProperty($propertyName);
        $docs = [];
        
        foreach ($property->getAttributes() as $attr) {
            $instance = $attr->newInstance();
            if ($instance instanceof DocumentedAttributeInterface) {
                $docs[$attr->getName()] = $instance->getDocPath();
            }
        }
        
        return $docs;
    }

    /**
     * Read all documentation for a class and its attributes
     * 
     * @param string|ReflectionClass $class Class name or ReflectionClass instance
     * @param string $projectRoot Project root directory
     * @return array<string, string> Map of attribute class name => documentation content
     */
    public static function readClassAttributeDocs(string|ReflectionClass $class, string $projectRoot): array
    {
        $reflection = $class instanceof ReflectionClass ? $class : new ReflectionClass($class);
        $docs = [];
        
        foreach ($reflection->getAttributes() as $attr) {
            $instance = $attr->newInstance();
            if ($instance instanceof DocumentedAttributeInterface) {
                $content = self::readDoc($instance, $projectRoot);
                if ($content !== null) {
                    $docs[$attr->getName()] = $content;
                }
            }
        }
        
        return $docs;
    }

    /**
     * Find all classes with DocumentedAttributeInterface attributes
     * 
     * @param string $namespace Namespace to search in
     * @param string $projectRoot Project root directory
     * @return array<string, array<string, string>> Map of class name => [attribute => doc path]
     */
    public static function findAllDocumentedAttributes(string $namespace, string $projectRoot): array
    {
        $result = [];
        $dir = $projectRoot . '/' . str_replace('\\', '/', $namespace);
        
        if (!is_dir($dir)) {
            return $result;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $className = self::getClassNameFromFile($file->getPathname(), $namespace);
                if ($className && class_exists($className)) {
                    try {
                        $reflection = new ReflectionClass($className);
                        $docs = self::getClassAttributeDocs($reflection);
                        if (!empty($docs)) {
                            $result[$className] = $docs;
                        }
                    } catch (\Throwable $e) {
                        // Skip invalid classes
                        continue;
                    }
                }
            }
        }
        
        return $result;
    }

    /**
     * Extract class name from file path
     * 
     * @param string $filePath File path
     * @param string $baseNamespace Base namespace
     * @return string|null Class name or null
     */
    private static function getClassNameFromFile(string $filePath, string $baseNamespace): ?string
    {
        // Simple implementation - in real scenario might need to parse file
        $relativePath = str_replace([$baseNamespace, '\\', '/'], ['', '\\', '\\'], $filePath);
        $relativePath = str_replace('.php', '', $relativePath);
        
        return $baseNamespace . '\\' . trim($relativePath, '\\');
    }
}

