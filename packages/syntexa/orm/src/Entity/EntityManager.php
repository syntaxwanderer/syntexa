<?php

declare(strict_types=1);

namespace Syntexa\Orm\Entity;

use PDO;
use Syntexa\Orm\Connection\ConnectionPool;
use Syntexa\Orm\Metadata\EntityMetadata;
use Syntexa\Orm\Metadata\EntityMetadataFactory;
use Syntexa\Orm\Query\QueryBuilder;
use Syntexa\Orm\Attributes\TimestampColumn;

/**
 * Stateless Entity Manager
 * Request-scoped - new instance for each request
 * All operations are stateless and use connection from pool
 */
class EntityManager
{
    /**
     * @var PDO|object Connection (PDO in CLI, PDOProxy in Swoole)
     */
    private $connection;
    private array $unitOfWork = []; // Track entities for flush
    /** @var array<class-string, EntityMetadata> */
    private array $entityMetadata = [];

    /**
     * @param PDO|object|null $connection Connection (PDO in CLI, PDOProxy in Swoole)
     */
    public function __construct($connection = null)
    {
        $this->connection = $connection ?? ConnectionPool::get();
    }

    /**
     * Find entity by ID
     */
    public function find(string $entityClass, int $id): ?object
    {
        $metadata = $this->getEntityMetadata($entityClass);
        $table = $metadata->tableName;
        $identifier = $metadata->identifier;

        if ($identifier === null) {
            throw new \RuntimeException("Entity {$entityClass} does not define an identifier column.");
        }

        $columnName = $identifier->columnName;
        $stmt = $this->connection->prepare("SELECT * FROM {$table} WHERE {$columnName} = :id");
        $stmt->execute([
            'id' => $identifier->convertToDatabaseValue($id),
        ]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        return $this->hydrateEntity($metadata, $data);
    }

    /**
     * Find entities by criteria
     */
    public function findBy(string $entityClass, array $criteria = [], ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $metadata = $this->getEntityMetadata($entityClass);
        $table = $metadata->tableName;
        
        $query = "SELECT * FROM {$table}";
        $params = [];
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                if (!isset($metadata->columns[$field])) {
                    throw new \RuntimeException("Unknown field '{$field}' for entity {$entityClass}");
                }
                $column = $metadata->columns[$field];
                $conditions[] = "{$column->columnName} = :{$field}";
                $params[$field] = $column->convertToDatabaseValue($value);
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if ($orderBy) {
            $orderParts = [];
            foreach ($orderBy as $field => $direction) {
                if (!isset($metadata->columns[$field])) {
                    throw new \RuntimeException("Unknown field '{$field}' for entity {$entityClass}");
                }
                $column = $metadata->columns[$field];
                $orderParts[] = "{$column->columnName} " . strtoupper($direction);
            }
            $query .= " ORDER BY " . implode(', ', $orderParts);
        }
        
        if ($limit !== null) {
            $query .= " LIMIT :limit";
            $params['limit'] = $limit;
        }
        
        if ($offset !== null) {
            $query .= " OFFSET :offset";
            $params['offset'] = $offset;
        }
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $entities = [];
        foreach ($results as $data) {
            $entities[] = $this->hydrateEntity($metadata, $data);
        }
        
        return $entities;
    }

    /**
     * Find one entity by criteria
     */
    public function findOneBy(string $entityClass, array $criteria): ?object
    {
        $results = $this->findBy($entityClass, $criteria, null, 1);
        return $results[0] ?? null;
    }

    /**
     * Persist entity (add to unit of work)
     */
    public function persist(object $entity): void
    {
        if (!is_object($entity)) {
            throw new \InvalidArgumentException('Entity must be an object');
        }
        
        $this->unitOfWork[] = $entity;
    }

    /**
     * Flush all persisted entities to database
     */
    public function flush(): void
    {
        foreach ($this->unitOfWork as $entity) {
            $this->saveEntity($entity);
        }
        
        $this->unitOfWork = [];
    }

    /**
     * Remove entity
     */
    public function remove(object $entity): void
    {
        $entityClass = get_class($entity);
        $metadata = $this->getEntityMetadata($entityClass);
        $table = $metadata->tableName;

        $identifier = $metadata->identifier;
        if ($identifier === null) {
            throw new \RuntimeException("Cannot remove entity {$entityClass} without identifier.");
        }

        $idValue = $identifier->getValue($entity);
        if ($idValue === null) {
            throw new \RuntimeException('Cannot remove entity without ID');
        }

        $columnName = $identifier->columnName;
        $stmt = $this->connection->prepare("DELETE FROM {$table} WHERE {$columnName} = :id");
        $stmt->execute(['id' => $identifier->convertToDatabaseValue($idValue)]);
    }

    /**
     * Create query builder
     */
    public function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->connection, $this);
    }

    /**
     * Get entity metadata (table name, etc.)
     */
    private function getEntityMetadata(string $entityClass): EntityMetadata
    {
        if (isset($this->entityMetadata[$entityClass])) {
            return $this->entityMetadata[$entityClass];
        }

        $metadata = EntityMetadataFactory::getMetadata($entityClass);
        $this->entityMetadata[$entityClass] = $metadata;
        return $metadata;
    }

    /**
     * Get default table name from class name
     */
    private function getDefaultTableName(string $entityClass): string
    {
        $parts = explode('\\', $entityClass);
        $className = end($parts);
        
        // Convert CamelCase to snake_case
        $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
        return $table . 's'; // Pluralize
    }

    /**
     * Hydrate entity from database row
     */
    private function hydrateEntity(EntityMetadata $metadata, array $data): object
    {
        $entityClass = $metadata->className;
        $entity = new $entityClass();

        foreach ($metadata->columns as $column) {
            $columnName = $column->columnName;
            $value = $data[$columnName] ?? null;
            $column->setValue($entity, $value);
        }

        return $entity;
    }

    /**
     * Save entity to database
     */
    private function saveEntity(object $entity): void
    {
        $entityClass = get_class($entity);
        $metadata = $this->getEntityMetadata($entityClass);
        $table = $metadata->tableName;

        $identifier = $metadata->identifier;
        $idValue = $identifier?->getValue($entity);
        $isNew = $idValue === null;

        $data = [];
        foreach ($metadata->columns as $column) {
            if ($column->isIdentifier && $column->isGenerated && $isNew) {
                continue;
            }

            if ($column->isIdentifier && !$isNew) {
                continue; // handled in WHERE clause
            }

            $value = $column->getValue($entity);

            if ($column->timestampType === TimestampColumn::TYPE_CREATED && $isNew) {
                if (!$value instanceof \DateTimeImmutable) {
                    $value = new \DateTimeImmutable();
                    $column->setPhpValue($entity, $value);
                }
            }

            if ($column->timestampType === TimestampColumn::TYPE_UPDATED) {
                $value = new \DateTimeImmutable();
                $column->setPhpValue($entity, $value);
            }

            $data[$column->columnName] = $column->convertToDatabaseValue($value);
        }

        if ($isNew) {
            $fields = array_keys($data);
            $placeholders = array_map(fn ($f) => ':' . $f, $fields);

            $returning = $identifier ? " RETURNING {$identifier->columnName}" : '';
            $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . "){$returning}";

            $stmt = $this->connection->prepare($sql);
            $stmt->execute($data);

            if ($identifier) {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && isset($result[$identifier->columnName])) {
                    $identifier->setValue($entity, $result[$identifier->columnName]);
                }
            }
        } else {
            if ($identifier === null) {
                throw new \RuntimeException("Cannot update entity {$entityClass} without identifier metadata.");
            }

            $setParts = [];
            foreach (array_keys($data) as $field) {
                $setParts[] = "{$field} = :{$field}";
            }

            $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$identifier->columnName} = :__identifier";
            $data['__identifier'] = $identifier->convertToDatabaseValue($identifier->getValue($entity));

            $stmt = $this->connection->prepare($sql);
            $stmt->execute($data);
        }
    }

    /**
     * Get connection (PDO in CLI, PDOProxy in Swoole)
     * @return PDO|object
     */
    public function getConnection()
    {
        return $this->connection;
    }
}

