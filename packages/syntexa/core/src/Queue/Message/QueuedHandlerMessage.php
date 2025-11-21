<?php

declare(strict_types=1);

namespace Syntexa\Core\Queue\Message;

use JsonException;

class QueuedHandlerMessage implements \JsonSerializable
{
    public function __construct(
        public string $handlerClass,
        public string $requestClass,
        public string $responseClass,
        public array $requestPayload,
        public array $responsePayload,
        public string $queuedAt = '',
    ) {
        $this->queuedAt = $queuedAt ?: date(DATE_ATOM);
    }

    public function jsonSerialize(): array
    {
        return [
            'handler' => $this->handlerClass,
            'requestClass' => $this->requestClass,
            'responseClass' => $this->responseClass,
            'requestPayload' => $this->requestPayload,
            'responsePayload' => $this->responsePayload,
            'queuedAt' => $this->queuedAt,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws JsonException
     */
    public static function fromJson(string $payload): self
    {
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        return new self(
            handlerClass: $data['handler'],
            requestClass: $data['requestClass'],
            responseClass: $data['responseClass'],
            requestPayload: $data['requestPayload'] ?? [],
            responsePayload: $data['responsePayload'] ?? [],
            queuedAt: $data['queuedAt'] ?? date(DATE_ATOM),
        );
    }
}

