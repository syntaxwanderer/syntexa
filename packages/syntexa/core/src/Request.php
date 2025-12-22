<?php

declare(strict_types=1);

namespace Syntexa\Core;

/**
 * HTTP Request representation
 */
readonly class Request
{
    public function __construct(
        public string $method,
        public string $uri,
        public array $headers,
        public array $query,
        public array $post,
        public array $server,
        public array $cookies,
        public ?string $content = null
    ) {}
    
    /**
     * Create Request using Factory (recommended)
     */
    public static function create(mixed $source = null): self
    {
        return RequestFactory::create($source);
    }
    
    
    public function getMethod(): string
    {
        return $this->method;
    }
    
    public function getUri(): string
    {
        return $this->uri;
    }
    
    public function getPath(): string
    {
        return parse_url($this->uri, PHP_URL_PATH) ?: '/';
    }
    
    public function getQueryString(): string
    {
        return parse_url($this->uri, PHP_URL_QUERY) ?: '';
    }
    
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }
    
    public function getQuery(string $key, string $default = ''): string
    {
        return $this->query[$key] ?? $default;
    }
    
    public function getPost(string $key, string $default = ''): string
    {
        return $this->post[$key] ?? $default;
    }
    
    public function getServer(string $key, string $default = ''): string
    {
        return $this->server[$key] ?? $default;
    }
    
    public function getCookie(string $key, string $default = ''): string
    {
        return $this->cookies[$key] ?? $default;
    }
    
    public function getContent(): ?string
    {
        return $this->content;
    }
    
    public function isMethod(string $method): bool
    {
        return strtoupper($this->method) === strtoupper($method);
    }
    
    public function isGet(): bool
    {
        return $this->isMethod('GET');
    }
    
    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }
    
    public function isPut(): bool
    {
        return $this->isMethod('PUT');
    }
    
    public function isDelete(): bool
    {
        return $this->isMethod('DELETE');
    }
    
    public function isAjax(): bool
    {
        return $this->getHeader('X-Requested-With') === 'XMLHttpRequest';
    }
    
    public function isJson(): bool
    {
        $contentType = $this->getHeader('Content-Type');
        return $contentType !== null && str_contains(strtolower($contentType), 'application/json');
    }
    
    /**
     * Get parsed JSON body as array
     * 
     * @return array|null Parsed JSON data or null if not JSON or parse failed
     */
    public function getJsonBody(): ?array
    {
        if (!$this->isJson() || !$this->content) {
            return null;
        }
        
        $data = json_decode($this->content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return is_array($data) ? $data : null;
    }
}
