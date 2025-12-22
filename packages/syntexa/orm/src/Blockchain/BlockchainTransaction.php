<?php

declare(strict_types=1);

namespace Syntexa\Orm\Blockchain;

/**
 * Blockchain Transaction
 * 
 * Represents a single transaction in the blockchain.
 * Contains entity operation data (save, update, delete).
 */
readonly class BlockchainTransaction
{
    public function __construct(
        public string $transactionId,  // SHA-256 hash
        public string $nodeId,
        public string $entityClass,
        public int $entityId,
        public string $operation,  // 'save', 'update', 'delete'
        public array $fields,  // Field values (hashed/encrypted)
        public \DateTimeImmutable $timestamp,
        public string $nonce,  // Base64-encoded random bytes
        public ?string $signature = null,  // Ed25519 signature
        public ?string $keyVersion = null,  // Key version for rotation
        public ?string $publicKey = null,  // Public key for verification
        public ?string $snapshotHash = null,  // For delete operations
        public ?string $reason = null,  // For delete operations
    ) {}

    /**
     * Convert to JSON for RabbitMQ
     */
    public function toJson(): string
    {
        return json_encode([
            'nodeId' => $this->nodeId,
            'transactionId' => $this->transactionId,
            'entityClass' => $this->entityClass,
            'entityId' => $this->entityId,
            'operation' => $this->operation,
            'fields' => $this->fields,
            'timestamp' => $this->timestamp->format('c'),
            'nonce' => $this->nonce,
            'signature' => $this->signature,
            'keyVersion' => $this->keyVersion,
            'publicKey' => $this->publicKey,
            'snapshotHash' => $this->snapshotHash,
            'reason' => $this->reason,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Create from JSON (from RabbitMQ)
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        
        return new self(
            transactionId: $data['transactionId'],
            nodeId: $data['nodeId'],
            entityClass: $data['entityClass'],
            entityId: $data['entityId'],
            operation: $data['operation'],
            fields: $data['fields'],
            timestamp: new \DateTimeImmutable($data['timestamp']),
            nonce: $data['nonce'],
            signature: $data['signature'] ?? null,
            keyVersion: $data['keyVersion'] ?? null,
            publicKey: $data['publicKey'] ?? null,
            snapshotHash: $data['snapshotHash'] ?? null,
            reason: $data['reason'] ?? null,
        );
    }

    /**
     * Generate transaction ID (SHA-256 hash)
     */
    public static function generateId(
        string $nodeId,
        string $entityClass,
        int $entityId,
        string $operation,
        array $fields,
        \DateTimeImmutable $timestamp,
        string $nonce
    ): string {
        $data = json_encode([
            'nodeId' => $nodeId,
            'entityClass' => $entityClass,
            'entityId' => $entityId,
            'operation' => $operation,
            'fields' => $fields,
            'timestamp' => $timestamp->format('c'),
            'nonce' => $nonce,
        ], JSON_THROW_ON_ERROR);
        
        return hash('sha256', $data);
    }
}

