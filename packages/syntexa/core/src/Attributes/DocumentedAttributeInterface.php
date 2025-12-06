<?php

declare(strict_types=1);

namespace Syntexa\Core\Attributes;

/**
 * Interface for attributes that provide documentation reference
 * 
 * All framework attributes should implement this interface to provide
 * a path to their documentation file. This helps AI assistants and
 * developers understand how to use the attribute correctly.
 */
interface DocumentedAttributeInterface
{
    /**
     * Get the path to the documentation file for this attribute
     * 
     * The path should be relative to the project root or an absolute path.
     * Documentation files should be in Markdown format.
     * 
     * @return string Path to documentation file (e.g., 'docs/attributes/AsRequest.md')
     */
    public function getDocPath(): string;
}

