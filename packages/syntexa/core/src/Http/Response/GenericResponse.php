<?php

declare(strict_types=1);

namespace Syntexa\Core\Http\Response;

use Syntexa\Core\Contract\ResponseInterface as ContractResponse;
use Syntexa\Core\Response as CoreResponse;

class GenericResponse implements ContractResponse
{
    public function __construct(
        private string $content = '',
        private int $statusCode = 200,
        private array $headers = []
    ) {
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function toCoreResponse(): CoreResponse
    {
        return new CoreResponse($this->content, $this->statusCode, $this->headers);
    }

    // Render pipeline hints (optional)
    private ?string $renderHandle = null;
    private array $renderContext = [];
    private ?\Syntexa\Core\Http\Response\ResponseFormat $renderFormat = null;
    private ?string $rendererClass = null;

    public function setRenderHandle(string $handle): self
    {
        $this->renderHandle = $handle;
        return $this;
    }

    public function getRenderHandle(): ?string
    {
        return $this->renderHandle;
    }

    public function setRenderContext(array $context): self
    {
        $this->renderContext = $context;
        return $this;
    }

    public function getRenderContext(): array
    {
        return $this->renderContext;
    }

    /**
     * Alias for setRenderContext for convenience
     */
    public function setContext(array $context): self
    {
        return $this->setRenderContext($context);
    }

    /**
     * Alias for getRenderContext for convenience
     */
    public function getContext(): array
    {
        return $this->getRenderContext();
    }

    public function setRenderFormat(?\Syntexa\Core\Http\Response\ResponseFormat $format): self
    {
        $this->renderFormat = $format;
        return $this;
    }

    public function getRenderFormat(): ?\Syntexa\Core\Http\Response\ResponseFormat
    {
        return $this->renderFormat;
    }

    public function setRendererClass(?string $rendererClass): self
    {
        $this->rendererClass = $rendererClass;
        return $this;
    }

    public function getRendererClass(): ?string
    {
        return $this->rendererClass;
    }
}


