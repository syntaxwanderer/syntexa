<?php

declare(strict_types=1);

namespace Syntexa\Orm\Entity;

use PDO;
use Syntexa\Orm\Connection\ConnectionPool;
use Syntexa\Orm\Query\QueryBuilder;

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
        $table = $metadata['table'];
        
        $stmt = $this->connection->prepare("SELECT * FROM {$table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        return $this->hydrateEntity($entityClass, $data);
    }

    /**
     * Find entities by criteria
     */
    public function findBy(string $entityClass, array $criteria = [], ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $metadata = $this->getEntityMetadata($entityClass);
        $table = $metadata['table'];
        
        $query = "SELECT * FROM {$table}";
        $params = [];
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if ($orderBy) {
            $orderParts = [];
            foreach ($orderBy as $field => $direction) {
                $orderParts[] = "{$field} " . strtoupper($direction);
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
            $entities[] = $this->hydrateEntity($entityClass, $data);
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
        $table = $metadata['table'];
        
        if (!method_exists($entity, 'getId') || $entity->getId() === null) {
            throw new \RuntimeException('Cannot remove entity without ID');
        }
        
        $stmt = $this->connection->prepare("DELETE FROM {$table} WHERE id = :id");
        $stmt->execute(['id' => $entity->getId()]);
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
    private function getEntityMetadata(string $entityClass): array
    {
        if (isset($this->entityMetadata[$entityClass])) {
            return $this->entityMetadata[$entityClass];
        }
        
        $reflection = new \ReflectionClass($entityClass);
        $attributes = $reflection->getAttributes(\Syntexa\Orm\Attributes\AsEntity::class);
        
        if (empty($attributes)) {
            throw new \RuntimeException("Entity {$entityClass} must have #[AsEntity] attribute");
        }
        
        $attr = $attributes[0]->newInstance();
        $table = $attr->table ?? $this->getDefaultTableName($entityClass);
        
        $this->entityMetadata[$entityClass] = [
            'table' => $table,
            'class' => $entityClass,
        ];
        
        return $this->entityMetadata[$entityClass];
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
    private function hydrateEntity(string $entityClass, array $data): object
    {
        $entity = new $entityClass();
        
        if (method_exists($entity, 'fromArray')) {
            $entity->fromArray($data);
        } else {
            // Fallback: set properties directly
            foreach ($data as $key => $value) {
                if (property_exists($entity, $key)) {
                    $entity->$key = $value;
                }
            }
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
        $table = $metadata['table'];
        
        $data = method_exists($entity, 'toArray') ? $entity->toArray() : get_object_vars($entity);
        
        // Remove null id if new
        $isNew = !method_exists($entity, 'isNew') || $entity->isNew();
        
        if ($isNew) {
            // Insert
            unset($data['id']);
            
            // Set timestamps
            $now = new \DateTimeImmutable();
            if (!isset($data['created_at']) && property_exists($entity, 'createdAt')) {
                $data['created_at'] = $now->format('Y-m-d H:i:s');
            }
            if (!isset($data['updated_at']) && property_exists($entity, 'updatedAt')) {
                $data['updated_at'] = $now->format('Y-m-d H:i:s');
            }
            
            $fields = array_keys($data);
            $placeholders = array_map(fn($f) => ":{$f}", $fields);
            
            $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ") RETURNING id";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($data);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result['id'])) {
                $entity->id = (int)$result['id'];
            }
        } else {
            // Update
            $id = method_exists($entity, 'getId') ? $entity->getId() : $entity->id;
            
            // Set updated timestamp
            $now = new \DateTimeImmutable();
            if (!isset($data['updated_at']) && property_exists($entity, 'updatedAt')) {
                $data['updated_at'] = $now->format('Y-m-d H:i:s');
            }
            
            unset($data['id'], $data['created_at']);
            
            $fields = array_keys($data);
            $setParts = array_map(fn($f) => "{$f} = :{$f}", $fields);
            
            $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE id = :id";
            $data['id'] = $id;
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

