<?php

declare(strict_types=1);

namespace Syntexa\Orm\Blockchain;

use PDO;

/**
 * Blockchain Storage
 *
 * Persists blockchain transactions into a separate blockchain database.
 * Uses simple PDO connection configured via BLOCKCHAIN_DB_* env vars.
 *
 * For now we only store raw transactions in `blockchain_transactions` table.
 * Block/mempool/BFT structures can be added incrementally.
 */
class BlockchainStorage
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly BlockchainConfig $config
    ) {
        if (!$config->hasBlockchainDb()) {
            throw new \RuntimeException('Blockchain DB is not configured (BLOCKCHAIN_DB_* env variables are required).');
        }
    }

    /**
     * Append transaction to blockchain storage
     */
    public function appendTransaction(BlockchainTransaction $transaction): void
    {
        $pdo = $this->getConnection();
        $this->ensureSchema($pdo);

        $sql = <<<SQL
INSERT INTO blockchain_transactions (
    transaction_id,
    block_id,
    block_height,
    node_id,
    entity_class,
    entity_id,
    operation,
    fields,
    timestamp,
    nonce,
    signature,
    created_at
) VALUES (
    :transaction_id,
    :block_id,
    :block_height,
    :node_id,
    :entity_class,
    :entity_id,
    :operation,
    :fields,
    :timestamp,
    :nonce,
    :signature,
    NOW()
)
ON CONFLICT (transaction_id) DO NOTHING
SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'transaction_id' => $transaction->transactionId,
            // For now we don't have finalized blocks - use placeholder values
            'block_id' => 'pending',          // Will be updated when block is finalized
            'block_height' => 0,              // Will be updated when block is finalized
            'node_id' => $transaction->nodeId,
            'entity_class' => $transaction->entityClass,
            'entity_id' => $transaction->entityId,
            'operation' => $transaction->operation,
            'fields' => json_encode($transaction->fields, JSON_THROW_ON_ERROR),
            'timestamp' => $transaction->timestamp->format('Y-m-d H:i:s'),
            'nonce' => $transaction->nonce,
            'signature' => $transaction->signature,
        ]);
    }

    /**
     * Ensure blockchain schema exists (minimal version)
     */
    private function ensureSchema(PDO $pdo): void
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        // Minimal schema: only blockchain_transactions table
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS blockchain_transactions (
    id BIGSERIAL PRIMARY KEY,
    transaction_id VARCHAR(64) UNIQUE NOT NULL,
    block_id VARCHAR(255) NOT NULL,
    block_height BIGINT NOT NULL,
    node_id VARCHAR(255) NOT NULL,
    entity_class VARCHAR(255) NOT NULL,
    entity_id INTEGER NOT NULL,
    operation VARCHAR(50) NOT NULL,
    fields JSONB NOT NULL,
    timestamp TIMESTAMP NOT NULL,
    nonce TEXT NOT NULL,
    signature TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
SQL);

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_blockchain_entity ON blockchain_transactions(entity_class, entity_id);');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_blockchain_height ON blockchain_transactions(block_height);');

        $initialized = true;
    }

    /**
     * Get PDO connection to blockchain database
     */
    private function getConnection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $host = $this->config->dbHost ?? 'localhost';
        $port = $this->config->dbPort ?? 5432;
        $dbname = $this->config->dbName ?? 'syntexa_blockchain';
        $user = $this->config->dbUser ?? 'postgres';
        $password = $this->config->dbPassword ?? '';

        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $dbname);

        $this->connection = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $this->connection;
    }
}


