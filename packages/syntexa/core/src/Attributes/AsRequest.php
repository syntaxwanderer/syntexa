<?php

declare(strict_types=1);

namespace Syntexa\Core\Attributes;

use Attribute;

/**
 * Marks a class as a request DTO with route information
 * 
 * This attribute tells Syntexa that this class should be treated as a request
 * and defines the route path, methods, and other options.
 * 
 * You can use environment variable references in any attribute value:
 * - `env::VAR_NAME` - reads from .env file, returns empty string if not set
 * - `env::VAR_NAME::default_value` - reads from .env file, returns default if not set (recommended)
 * - `env::VAR_NAME:default_value` - legacy format, also supported for backward compatibility
 * 
 * The double colon format (`::`) is recommended because it allows colons in default values.
 * 
 * Example:
 * ```php
 * #[AsRequest(
 *     doc: 'docs/attributes/AsRequest.md',
 *     path: 'env::API_LOGIN_PATH::/api/login',
 *     methods: ['POST'],
 *     name: 'env::API_LOGIN_ROUTE_NAME::api.login',
 *     responseWith: 'env::API_LOGIN_RESPONSE_CLASS::LoginApiResponse'
 * )]
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AsRequest implements DocumentedAttributeInterface
{
    use DocumentedAttributeTrait;

    public readonly ?string $doc;

    public function __construct(
        ?string $doc = null,
        public ?string $base = null,
        public ?string $responseWith = null,
        public ?string $path = null,
        public ?array $methods = null,
        public ?string $name = null,
        public ?array $requirements = null,
        public ?array $defaults = null,
        public ?array $options = null,
        public ?array $tags = null,
        public ?bool $public = null,
        public string $protocol = 'http',
    ) {
        $this->doc = $doc;
    }

    public function getDocPath(): string
    {
        return $this->doc ?? 'docs/en/attributes/AsRequest.md';
    }
}
