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
use Syntexa\Orm\Attributes\OneToOne;
use Syntexa\Orm\Attributes\OneToMany;
use Syntexa\Orm\Attributes\ManyToOne;
use Syntexa\Orm\Attributes\ManyToMany;
use Syntexa\Orm\Attributes\JoinColumn;

class EntityMetadataFactory
{
    /** @var array<class-string, EntityMetadata> */
    private static array $cache = [];
    /** @var array<class-string, class-string> domainClass => entityClass */
    private static array $domainToEntity = [];

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

        // Extract relationship metadata
        $relationships = self::extractRelationships($reflection);

        $metadata = new EntityMetadata(
            className: $className,
            tableName: $table,
            columns: $columns,
            identifier: $identifier,
            domainClass: $entityAttr->domainClass,
            mapperClass: $entityAttr->mapper,
            repositoryClass: $entityAttr->repositoryClass,
            relationships: $relationships
        );

        if ($entityAttr->domainClass) {
            self::$domainToEntity[$entityAttr->domainClass] = $className;
        }

        self::$cache[$className] = $metadata;
        return $metadata;
    }

    public static function resolveEntityClassForDomain(string $domainClass): ?string
    {
        if (isset(self::$domainToEntity[$domainClass])) {
            return self::$domainToEntity[$domainClass];
        }

        // Fallback discovery: scan declared classes for #[AsEntity] with matching domainClass
        foreach (get_declared_classes() as $class) {
            // Skip non-ORM classes quickly
            if (!str_contains($class, '\\')) {
                continue;
            }
            try {
                $ref = new ReflectionClass($class);
            } catch (\ReflectionException) {
                continue;
            }
            $attr = self::getAttributeInstance($ref, AsEntity::class);
            if ($attr && $attr->domainClass === $domainClass) {
                // Build metadata to populate caches (including domainToEntity)
                self::getMetadata($class);
                return self::$domainToEntity[$domainClass] ?? null;
            }
        }

        return null;
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

    /**
     * Extract relationship metadata from entity properties
     * 
     * @return array<string, RelationshipMetadata>
     */
    private static function extractRelationships(ReflectionClass $reflection): array
    {
        $relationships = [];

        foreach ($reflection->getProperties() as $property) {
            $oneToOne = self::getAttributeInstance($property, OneToOne::class);
            $oneToMany = self::getAttributeInstance($property, OneToMany::class);
            $manyToOne = self::getAttributeInstance($property, ManyToOne::class);
            $manyToMany = self::getAttributeInstance($property, ManyToMany::class);

            $joinColumnAttr = self::getAttributeInstance($property, JoinColumn::class);
            $joinColumn = null;
            if ($joinColumnAttr) {
                $joinColumn = new JoinColumnMetadata(
                    name: $joinColumnAttr->name,
                    referencedColumnName: $joinColumnAttr->referencedColumnName,
                    nullable: $joinColumnAttr->nullable
                );
            }

            if ($oneToOne) {
                $relationships[$property->getName()] = new RelationshipMetadata(
                    propertyName: $property->getName(),
                    type: 'OneToOne',
                    targetEntity: $oneToOne->targetEntity,
                    mappedBy: $oneToOne->mappedBy,
                    joinColumn: $joinColumn,
                    fetch: $oneToOne->fetch,
                    cascade: $oneToOne->cascade,
                    orphanRemoval: $oneToOne->orphanRemoval
                );
            } elseif ($oneToMany) {
                $relationships[$property->getName()] = new RelationshipMetadata(
                    propertyName: $property->getName(),
                    type: 'OneToMany',
                    targetEntity: $oneToMany->targetEntity,
                    mappedBy: $oneToMany->mappedBy,
                    joinColumn: $joinColumn,
                    fetch: $oneToMany->fetch,
                    cascade: $oneToMany->cascade,
                    orphanRemoval: $oneToMany->orphanRemoval
                );
            } elseif ($manyToOne) {
                $relationships[$property->getName()] = new RelationshipMetadata(
                    propertyName: $property->getName(),
                    type: 'ManyToOne',
                    targetEntity: $manyToOne->targetEntity,
                    mappedBy: $manyToOne->inversedBy, // ManyToOne uses inversedBy
                    joinColumn: $joinColumn,
                    fetch: $manyToOne->fetch,
                    cascade: $manyToOne->cascade,
                    orphanRemoval: false // ManyToOne doesn't support orphanRemoval
                );
            } elseif ($manyToMany) {
                $relationships[$property->getName()] = new RelationshipMetadata(
                    propertyName: $property->getName(),
                    type: 'ManyToMany',
                    targetEntity: $manyToMany->targetEntity,
                    mappedBy: null, // ManyToMany uses joinTable, not mappedBy
                    joinColumn: $joinColumn,
                    fetch: $manyToMany->fetch,
                    cascade: $manyToMany->cascade,
                    orphanRemoval: false // ManyToMany doesn't support orphanRemoval
                );
            }
        }

        return $relationships;
    }
}


