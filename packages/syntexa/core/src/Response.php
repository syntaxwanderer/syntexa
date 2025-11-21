<?php

declare(strict_types=1);

namespace Syntexa\Core;

use Syntexa\Core\Contract\ResponseInterface;

/**
 * HTTP Response representation
 */
readonly class Response implements ResponseInterface
{
    public function __construct(
        public string $content,
        public int $statusCode = 200,
        public array $headers = []
    ) {}
    
    public static function json(array $data, int $statusCode = 200): self
    {
        return new self(
            content: json_encode($data),
            statusCode: $statusCode,
            headers: ['Content-Type' => 'application/json']
        );
    }
    
    public static function text(string $content, int $statusCode = 200): self
    {
        return new self(
            content: $content,
            statusCode: $statusCode,
            headers: ['Content-Type' => 'text/plain']
        );
    }
    
    public static function html(string $content, int $statusCode = 200): self
    {
        return new self(
            content: $content,
            statusCode: $statusCode,
            headers: ['Content-Type' => 'text/html; charset=utf-8']
        );
    }
    
    public static function notFound(string $message = 'Not Found'): self
    {
        return self::json([
            'error' => 'Not Found',
            'message' => $message
        ], 404);
    }
    
    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self(
            content: '',
            statusCode: $statusCode,
            headers: ['Location' => $url]
        );
    }
    
    public function getContent(): string
    {
        return $this->content;
    }
    
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    
    public function getHeaders(): array
    {
        return $this->headers;
    }
    
    public function send(): void
    {
        // Set status code
        http_response_code($this->statusCode);
        
        // Set headers
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        
        // Output content
        echo $this->content;
    }
}
