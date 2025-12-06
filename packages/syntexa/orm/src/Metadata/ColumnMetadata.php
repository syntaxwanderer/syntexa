<?php

declare(strict_types=1);

namespace Syntexa\Orm\Metadata;

use ReflectionProperty;

class ColumnMetadata
{
    public function __construct(
        public readonly string $propertyName,
        public readonly string $columnName,
        public readonly string $type,
        public readonly bool $nullable,
        public readonly bool $unique,
        public readonly ?int $length,
        public readonly mixed $default,
        public readonly bool $isIdentifier,
        public readonly bool $isGenerated,
        public readonly ?string $timestampType,
        private readonly ReflectionProperty $property
    ) {
        $this->property->setAccessible(true);
    }

    public function getValue(object $entity): mixed
    {
        return $this->property->getValue($entity);
    }

    public function setValue(object $entity, mixed $value): void
    {
        $this->property->setValue($entity, $this->convertToPhpValue($value));
    }

    public function setPhpValue(object $entity, mixed $value): void
    {
        $this->property->setValue($entity, $value);
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            'datetime_immutable' => $value instanceof \DateTimeImmutable
                ? $value->format('Y-m-d H:i:s')
                : (string) $value,
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            'bool' => $value ? 1 : 0,
            default => $value,
        };
    }

    public function convertToPhpValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            'datetime_immutable' => $value instanceof \DateTimeImmutable
                ? $value
                : new \DateTimeImmutable((string) $value),
            'json' => is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value,
            'bool' => (bool) $value,
            'int' => (int) $value,
            default => $value,
        };
    }
}


