<?php

declare(strict_types=1);

namespace Syntexa\Core\Attributes;

use Attribute;
use ReflectionClass;

/**
 * Documentation attribute for linking code to documentation files
 * 
 * This attribute can be used alongside any other attribute to provide
 * a reference to documentation. It supports automatic path generation
 * based on conventions or explicit path specification.
 * 
 * Usage examples:
 * 
 * ```php
 * // Automatic path (searches for docs/attributes/{AttributeClass}.md)
 * #[Documentation]
 * #[AsRequest(path: '/api/users')]
 * class UserListRequest implements RequestInterface {}
 * 
 * // Explicit path
 * #[Documentation(path: 'docs/custom/MyRequest.md')]
 * #[AsRequest(path: '/api/users')]
 * class CustomRequest implements RequestInterface {}
 * 
 * // With additional metadata
 * #[Documentation(
 *     path: 'docs/attributes/AsRequest.md',
 *     version: '1.0.0',
 *     examples: ['examples/AsRequestExample.php']
 * )]
 * #[AsRequest(path: '/api/users')]
 * class UserListRequest implements RequestInterface {}
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
class Documentation
{
    /**
     * Path to documentation file (relative to project root)
     * If null, will be auto-generated based on conventions
     */
    public readonly ?string $path;

    /**
     * Documentation version
     */
    public readonly ?string $version;

    /**
     * Author of the documentation
     */
    public readonly ?string $author;

    /**
     * Array of example file paths
     * 
     * @var array<string>|null
     */
    public readonly ?array $examples;

    /**
     * Related classes/attributes
     * 
     * @var array<string>|null
     */
    public readonly ?array $related;

    public function __construct(
        ?string $path = null,
        ?string $version = null,
        ?string $author = null,
        ?array $examples = null,
        ?array $related = null,
    ) {
        $this->path = $path;
        $this->version = $version;
        $this->author = $author;
        $this->examples = $examples;
        $this->related = $related;
    }

    /**
     * Get documentation path, auto-generating if not set
     * 
     * @param ReflectionClass|null $reflection Reflection of the class/property/method
     * @param string|null $attributeClass Name of the main attribute class (e.g., AsRequest::class)
     * @return string Documentation file path
     */
    public function getPath(?ReflectionClass $reflection = null, ?string $attributeClass = null): string
    {
        if ($this->path !== null) {
            return $this->path;
        }

        // Auto-generate path based on conventions
        return $this->generateDefaultPath($reflection, $attributeClass);
    }

    /**
     * Generate default documentation path based on conventions
     * 
     * Convention: docs/attributes/{AttributeClassName}.md
     * 
     * @param ReflectionClass|null $reflection
     * @param string|null $attributeClass
     * @return string
     */
    private function generateDefaultPath(?ReflectionClass $reflection, ?string $attributeClass): string
    {
        // If attribute class is provided, use it
        if ($attributeClass !== null) {
            $className = $this->getShortClassName($attributeClass);
            return "docs/attributes/{$className}.md";
        }

        // Otherwise, try to infer from reflection
        if ($reflection !== null) {
            // Get the first attribute that is not Documentation
            $attributes = $reflection->getAttributes();
            foreach ($attributes as $attr) {
                $attrClass = $attr->getName();
                if ($attrClass !== self::class) {
                    $className = $this->getShortClassName($attrClass);
                    return "docs/attributes/{$className}.md";
                }
            }
        }

        // Fallback
        return "docs/attributes/Unknown.md";
    }

    /**
     * Get short class name from fully qualified name
     */
    private function getShortClassName(string $fullyQualifiedName): string
    {
        $parts = explode('\\', $fullyQualifiedName);
        return end($parts);
    }

    /**
     * Check if documentation file exists
     * 
     * @param string $projectRoot Project root directory
     * @return bool
     */
    public function exists(string $projectRoot): bool
    {
        $path = $this->getPath();
        $fullPath = $projectRoot . '/' . ltrim($path, '/');
        return file_exists($fullPath);
    }

    /**
     * Read documentation content
     * 
     * @param string $projectRoot Project root directory
     * @return string|null Documentation content or null if not found
     */
    public function read(string $projectRoot): ?string
    {
        if (!$this->exists($projectRoot)) {
            return null;
        }

        $path = $this->getPath();
        $fullPath = $projectRoot . '/' . ltrim($path, '/');
        
        return file_get_contents($fullPath) ?: null;
    }
}

