<?php

declare(strict_types=1);

namespace Syntexa\Orm\Metadata;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use Syntexa\Orm\Attributes\AsEntity;
use Syntexa\Orm\Attributes\Column;
use Syntexa\Orm\Attributes\GeneratedValue;
use Syntexa\Orm\Attributes\Id;
use Syntexa\Orm\Attributes\TimestampColumn;

class EntityMetadataFactory
{
    /** @var array<class-string, EntityMetadata> */
    private static array $cache = [];

    public static function getMetadata(string $className): EntityMetadata
    {
        if (isset(self::$cache[$className])) {
            return self::$cache[$className];
        }

        $reflection = new ReflectionClass($className);

        $entityAttr = self::getAttributeInstance($reflection, AsEntity::class);
        if (!$entityAttr instanceof AsEntity) {
            throw new \RuntimeException("Entity {$className} must have #[AsEntity] attribute");
        }

        $table = $entityAttr->table ?? self::defaultTableName($className);

        $columns = [];
        $identifier = null;

        foreach ($reflection->getProperties() as $property) {
            $columnAttr = self::getAttributeInstance($property, Column::class);
            $idAttr = self::getAttributeInstance($property, Id::class);

            if ($columnAttr === null && $idAttr === null) {
                continue;
            }

            $columnName = $columnAttr?->name ?? self::defaultColumnName($property->getName());
            $type = $columnAttr?->type ?? 'string';
            $nullable = $columnAttr?->nullable ?? false;
            $unique = $columnAttr?->unique ?? false;
            $length = $columnAttr?->length;
            $default = $columnAttr?->default;

            $generatedAttr = self::getAttributeInstance($property, GeneratedValue::class);
            $timestampAttr = self::getAttributeInstance($property, TimestampColumn::class);

            $columnMetadata = new ColumnMetadata(
                propertyName: $property->getName(),
                columnName: $columnName,
                type: $type,
                nullable: $nullable,
                unique: $unique,
                length: $length,
                default: $default,
                isIdentifier: $idAttr !== null,
                isGenerated: ($generatedAttr?->strategy ?? GeneratedValue::STRATEGY_NONE) !== GeneratedValue::STRATEGY_NONE,
                timestampType: $timestampAttr?->type,
                property: $property
            );

            if ($columnMetadata->isIdentifier) {
                $identifier = $columnMetadata;
            }

            $columns[$property->getName()] = $columnMetadata;
        }

        if (empty($columns)) {
            throw new \RuntimeException("Entity {$className} has no mapped properties. Add #[Column] or #[Id].");
        }

        $metadata = new EntityMetadata(
            className: $className,
            tableName: $table,
            columns: $columns,
            identifier: $identifier
        );

        self::$cache[$className] = $metadata;
        return $metadata;
    }

    private static function defaultTableName(string $entityClass): string
    {
        $parts = explode('\\', $entityClass);
        $className = end($parts);
        return self::camelToSnake($className) . 's';
    }

    private static function defaultColumnName(string $property): string
    {
        return self::camelToSnake($property);
    }

    private static function camelToSnake(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    private static function getAttributeInstance(ReflectionClass|ReflectionProperty $reflection, string $attributeClass): ?object
    {
        $attributes = $reflection->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF);
        if (empty($attributes)) {
            return null;
        }
        return $attributes[0]->newInstance();
    }
}


