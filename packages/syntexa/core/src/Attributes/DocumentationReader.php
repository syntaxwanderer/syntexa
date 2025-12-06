<?php

declare(strict_types=1);

namespace Syntexa\Core\Attributes;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Utility for reading Documentation attributes
 * 
 * This class helps AI assistants and tools to automatically
 * discover and read documentation from Documentation attributes.
 */
class DocumentationReader
{
    /**
     * Get Documentation attribute from a class
     * 
     * @param string|ReflectionClass $class
     * @return Documentation|null
     */
    public static function getClassDocumentation(string|ReflectionClass $class): ?Documentation
    {
        $reflection = $class instanceof ReflectionClass ? $class : new ReflectionClass($class);
        
        $attributes = $reflection->getAttributes(Documentation::class);
        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Get Documentation attribute from a property
     * 
     * @param ReflectionClass $class
     * @param string $propertyName
     * @return Documentation|null
     */
    public static function getPropertyDocumentation(ReflectionClass $class, string $propertyName): ?Documentation
    {
        if (!$class->hasProperty($propertyName)) {
            return null;
        }

        $property = $class->getProperty($propertyName);
        $attributes = $property->getAttributes(Documentation::class);
        
        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Get Documentation attribute from a method
     * 
     * @param ReflectionClass $class
     * @param string $methodName
     * @return Documentation|null
     */
    public static function getMethodDocumentation(ReflectionClass $class, string $methodName): ?Documentation
    {
        if (!$class->hasMethod($methodName)) {
            return null;
        }

        $method = $class->getMethod($methodName);
        $attributes = $method->getAttributes(Documentation::class);
        
        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Get all Documentation attributes from a class and its members
     * 
     * @param string|ReflectionClass $class
     * @return array<string, Documentation> Map of location => Documentation
     */
    public static function getAllDocumentation(string|ReflectionClass $class): array
    {
        $reflection = $class instanceof ReflectionClass ? $class : new ReflectionClass($class);
        $docs = [];

        // Class-level documentation
        $classDoc = self::getClassDocumentation($reflection);
        if ($classDoc !== null) {
            $docs['class'] = $classDoc;
        }

        // Property-level documentation
        foreach ($reflection->getProperties() as $property) {
            $propDoc = self::getPropertyDocumentation($reflection, $property->getName());
            if ($propDoc !== null) {
                $docs['property:' . $property->getName()] = $propDoc;
            }
        }

        // Method-level documentation
        foreach ($reflection->getMethods() as $method) {
            $methodDoc = self::getMethodDocumentation($reflection, $method->getName());
            if ($methodDoc !== null) {
                $docs['method:' . $method->getName()] = $methodDoc;
            }
        }

        return $docs;
    }

    /**
     * Read documentation content for a class
     * 
     * @param string|ReflectionClass $class
     * @param string $projectRoot
     * @return string|null
     */
    public static function readClassDocumentation(string|ReflectionClass $class, string $projectRoot): ?string
    {
        $doc = self::getClassDocumentation($class);
        if ($doc === null) {
            return null;
        }

        return $doc->read($projectRoot);
    }

    /**
     * Find Documentation attribute associated with another attribute
     * 
     * This is useful when you have:
     * ```php
     * #[Documentation]
     * #[AsRequest(path: '/api/users')]
     * ```
     * 
     * @param ReflectionClass $reflection
     * @param string $attributeClass The class of the attribute to find documentation for
     * @return Documentation|null
     */
    public static function findDocumentationForAttribute(
        ReflectionClass $reflection,
        string $attributeClass
    ): ?Documentation {
        // First, try to find Documentation attribute on the same element
        $doc = self::getClassDocumentation($reflection);
        if ($doc !== null) {
            return $doc;
        }

        // If not found, try to auto-generate path based on attribute class
        $doc = new Documentation();
        $path = $doc->getPath($reflection, $attributeClass);
        
        // Create a new Documentation instance with the generated path
        return new Documentation(path: $path);
    }
}

