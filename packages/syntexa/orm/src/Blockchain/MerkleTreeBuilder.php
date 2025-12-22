<?php

declare(strict_types=1);

namespace Syntexa\Orm\Blockchain;

/**
 * Merkle Tree Builder
 * 
 * Builds Merkle tree from transactions deterministically.
 * Same transactions → same Merkle root (always).
 */
class MerkleTreeBuilder
{
    /**
     * Build Merkle tree from transactions and return root hash
     * 
     * @param array<BlockchainTransaction> $transactions
     * @return string Merkle root hash
     */
    public function buildTree(array $transactions): string
    {
        if (empty($transactions)) {
            return hash('sha256', '');
        }

        // Convert transactions to hashes
        $hashes = array_map(
            fn(BlockchainTransaction $tx) => hash('sha256', $tx->toJson()),
            $transactions
        );

        // Build tree by pairing hashes
        while (count($hashes) > 1) {
            $newHashes = [];
            for ($i = 0; $i < count($hashes); $i += 2) {
                $left = $hashes[$i];
                $right = $hashes[$i + 1] ?? $hashes[$i]; // Duplicate last if odd
                $newHashes[] = hash('sha256', $left . $right);
            }
            $hashes = $newHashes;
        }

        return $hashes[0]; // Root hash
    }

    /**
     * Verify Merkle root matches transactions
     */
    public function verifyRoot(array $transactions, string $expectedRoot): bool
    {
        $calculatedRoot = $this->buildTree($transactions);
        return $calculatedRoot === $expectedRoot;
    }
}

