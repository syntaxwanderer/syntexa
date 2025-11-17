<?php

declare(strict_types=1);

namespace Syntexa\Core\Config;

use Syntexa\Core\Environment;

/**
 * Resolves environment variable references in attribute values.
 * 
 * Supports formats:
 * - `env::VAR_NAME` - reads from environment, returns empty string if not set
 * - `env::VAR_NAME::default_value` - reads from environment, returns default if not set (recommended)
 * - `env::VAR_NAME:default_value` - legacy format, also supported for backward compatibility
 * 
 * The double colon format (`::`) is recommended because it allows colons in default values.
 */
class EnvValueResolver
{
    /**
     * Resolve a value that may contain an environment variable reference.
     * 
     * @param mixed $value The value to resolve (string, array, or other)
     * @return mixed The resolved value
     */
    public static function resolve(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::resolveString($value);
        }
        
        if (is_array($value)) {
            return array_map(fn($item) => self::resolve($item), $value);
        }
        
        return $value;
    }
    
    /**
     * Resolve a string value that may contain env:: references.
     * 
     * @param string $value The string value
     * @return string The resolved value (empty string if env var not found and no default)
     */
    private static function resolveString(string $value): string
    {
        // Check if value matches pattern: env::VAR_NAME or env::VAR_NAME::default
        if (!str_starts_with($value, 'env::')) {
            return $value;
        }
        
        // Remove 'env::' prefix
        $rest = substr($value, 5);
        
        // Check if default value is provided using double colon (recommended format)
        if (str_contains($rest, '::')) {
            [$varName, $default] = explode('::', $rest, 2);
            $varName = trim($varName);
            $default = trim($default);
            
            $resolved = Environment::getEnvValue($varName, $default);
            return $resolved ?? $default;
        }
        
        // Check for legacy format with single colon (backward compatibility)
        if (str_contains($rest, ':')) {
            [$varName, $default] = explode(':', $rest, 2);
            $varName = trim($varName);
            $default = trim($default);
            
            $resolved = Environment::getEnvValue($varName, $default);
            return $resolved ?? $default;
        }
        
        // No default value provided - return empty string if not found
        $resolved = Environment::getEnvValue($rest, '');
        return $resolved ?? '';
    }
}

