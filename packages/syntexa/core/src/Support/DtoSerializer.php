<?php

declare(strict_types=1);

namespace Syntexa\Core\Support;

use ReflectionClass;
use ReflectionProperty;

class DtoSerializer
{
    public static function toArray(object $dto): array
    {
        $reflection = new ReflectionClass($dto);
        $data = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isInitialized($dto)) {
                continue;
            }

            $value = $property->getValue($dto);
            $data[$property->getName()] = self::normalize($value);
        }

        return $data;
    }

    public static function hydrate(object $dto, array $payload): object
    {
        $reflection = new ReflectionClass($dto);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            if (!array_key_exists($name, $payload)) {
                continue;
            }

            $property->setValue($dto, $payload[$name]);
        }

        return $dto;
    }

    private static function normalize(mixed $value): mixed
    {
        return match (true) {
            is_object($value) => method_exists($value, '__toString')
                ? (string) $value
                : self::toArray($value),
            is_array($value) => array_map(fn ($item) => self::normalize($item), $value),
            default => $value,
        };
    }
}

