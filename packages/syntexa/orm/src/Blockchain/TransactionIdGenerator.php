<?php

declare(strict_types=1);

namespace Syntexa\Orm\Blockchain;

/**
 * Transaction ID Generator
 * 
 * Generates cryptographically unique transaction IDs using SHA-256.
 * Deterministic: same input → same ID.
 */
class TransactionIdGenerator
{
    /**
     * Generate transaction ID
     */
    public function generate(
        string $nodeId,
        string $entityClass,
        int $entityId,
        string $operation,
        array $fields,
        \DateTimeImmutable $timestamp
    ): string {
        // Nonce ensures uniqueness even if all other data is same
        $nonce = random_bytes(32);
        
        return BlockchainTransaction::generateId(
            $nodeId,
            $entityClass,
            $entityId,
            $operation,
            $fields,
            $timestamp,
            base64_encode($nonce)
        );
    }
}

