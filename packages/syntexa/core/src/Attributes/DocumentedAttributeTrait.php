<?php

declare(strict_types=1);

namespace Syntexa\Core\Attributes;

/**
 * Trait that provides default implementation for DocumentedAttributeInterface
 * 
 * Use this trait in attribute classes to automatically implement
 * the documentation path functionality.
 * 
 * Note: Classes using this trait must define a public property `doc` of type string.
 */
trait DocumentedAttributeTrait
{
    /**
     * Get the path to the documentation file
     * 
     * Note: Classes using this trait should override this method
     * to provide a default path when doc is null.
     */
    public function getDocPath(): string
    {
        return $this->doc ?? '';
    }

    /**
     * Validate that documentation file exists
     * 
     * @param string $projectRoot Project root directory
     * @throws \RuntimeException If documentation file doesn't exist
     */
    public function validateDocExists(string $projectRoot): void
    {
        $docPath = $this->getDocPath();
        
        // Handle relative paths
        if (!str_starts_with($docPath, '/')) {
            $docPath = $projectRoot . '/' . ltrim($docPath, '/');
        }
        
        if (!file_exists($docPath)) {
            throw new \RuntimeException(
                "Documentation file not found for attribute " . static::class . ": {$docPath}\n" .
                "Please create the documentation file or update the 'doc' parameter in the attribute."
            );
        }
    }
}

