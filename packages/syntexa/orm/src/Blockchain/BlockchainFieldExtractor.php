<?php

declare(strict_types=1);

namespace Syntexa\Orm\Blockchain;

use ReflectionException;
use Syntexa\Orm\Attributes\BlockchainField;
use Syntexa\Orm\Metadata\EntityMetadata;
use ReflectionClass;
use ReflectionProperty;

/**
 * Extracts blockchain fields from entity
 * 
 * Only fields marked with #[BlockchainField] are included.
 */
class BlockchainFieldExtractor
{
    /**
     * Extract blockchain fields from entity
     *
     * @return array<string, mixed> Field values (hashed/encrypted)
     * @throws ReflectionException
     */
    public function extractFields(object $entity, EntityMetadata $metadata): array
    {
        $fields = [];

        $reflection = new ReflectionClass($metadata->className);

        foreach ($metadata->columns as $column) {
            $property = $reflection->getProperty($column->propertyName);
            $blockchainAttr = $this->getBlockchainAttribute($property);
            
            if ($blockchainAttr === null) {
                continue; // Skip fields without #[BlockchainField]
            }
            
            $value = $column->getValue($entity);
            
            // Hash the value (encryption happens here if encrypt=true)
            $hashedValue = $this->hashValue($value, $blockchainAttr);
            
            $fields[$column->propertyName] = $hashedValue;
        }
        
        return $fields;
    }

    /**
     * Get #[BlockchainField] attribute from property
     */
    private function getBlockchainAttribute(ReflectionProperty $property): ?BlockchainField
    {
        $attributes = $property->getAttributes(BlockchainField::class);
        if (empty($attributes)) {
            return null;
        }
        
        return $attributes[0]->newInstance();
    }

    /**
     * Hash value (with optional encryption)
     */
    private function hashValue(mixed $value, BlockchainField $attr): string
    {
        // Serialize value
        $serialized = $this->serializeValue($value);
        
        // Encrypt if needed
        if ($attr->encrypt) {
            $serialized = $this->encrypt($serialized, $attr->encryptionKeyId);
        }
        
        // Hash encrypted/serialized value
        return hash('sha256', $serialized);
    }

    /**
     * Serialize value to string
     */
    private function serializeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        
        if (is_scalar($value)) {
            return (string) $value;
        }
        
        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }
        
        return serialize($value);
    }

    /**
     * Encrypt value (placeholder - implement with KMS)
     */
    private function encrypt(string $value, ?string $keyId): string
    {
        // TODO: Implement encryption with KMS
        // For now, return as-is (encryption will be added later)
        return $value;
    }
}

