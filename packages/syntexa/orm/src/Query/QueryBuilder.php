<?php

declare(strict_types=1);

namespace Syntexa\Orm\Query;

use PDO;
use Syntexa\Orm\Entity\EntityManager;

/**
 * Query Builder for DQL-like queries
 */
class QueryBuilder
{
    private string $select = '';
    private string $from = '';
    private array $joins = [];
    private array $where = [];
    private array $params = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $aliases = [];

    /**
     * @param PDO|object $connection Connection (PDO in CLI, PDOProxy in Swoole)
     */
    public function __construct(
        private $connection,
        private EntityManager $entityManager
    ) {
    }

    /**
     * Select fields
     */
    public function select(string $select): self
    {
        $this->select = $select;
        return $this;
    }

    /**
     * From entity
     */
    public function from(string $entityClass, string $alias = 'e'): self
    {
        // Get table name from entity metadata
        $reflection = new \ReflectionClass($entityClass);
        $attributes = $reflection->getAttributes(\Syntexa\Orm\Attributes\AsEntity::class);
        
        if (empty($attributes)) {
            throw new \RuntimeException("Entity {$entityClass} must have #[AsEntity] attribute");
        }
        
        $attr = $attributes[0]->newInstance();
        $table = $attr->table ?? $this->getDefaultTableName($entityClass);
        
        $this->from = "{$table} AS {$alias}";
        $this->aliases[$alias] = $table;
        return $this;
    }

    /**
     * INNER JOIN
     */
    public function join(string $tableOrExpr, string $alias, ?string $on = null): self
    {
        $clause = "INNER JOIN {$tableOrExpr} AS {$alias}";
        if ($on) {
            $clause .= " ON {$on}";
        }
        $this->joins[] = $clause;
        $this->aliases[$alias] = $tableOrExpr;
        return $this;
    }

    /**
     * LEFT JOIN
     */
    public function leftJoin(string $tableOrExpr, string $alias, ?string $on = null): self
    {
        $clause = "LEFT JOIN {$tableOrExpr} AS {$alias}";
        if ($on) {
            $clause .= " ON {$on}";
        }
        $this->joins[] = $clause;
        $this->aliases[$alias] = $tableOrExpr;
        return $this;
    }

    /**
     * Add WHERE condition
     */
    public function where(string $condition, mixed $value = null): self
    {
        if ($value !== null) {
            $paramName = 'param_' . count($this->params);
            $this->where[] = str_replace('?', ":{$paramName}", $condition);
            $this->params[$paramName] = $value;
        } else {
            $this->where[] = $condition;
        }
        return $this;
    }

    /**
     * Add AND WHERE condition
     */
    public function andWhere(string $condition, mixed $value = null): self
    {
        return $this->where($condition, $value);
    }

    /**
     * Add ORDER BY
     */
    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->orderBy[] = "{$field} " . strtoupper($direction);
        return $this;
    }

    /**
     * Set LIMIT
     */
    public function setMaxResults(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set OFFSET
     */
    public function setFirstResult(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Set parameter
     */
    public function setParameter(string $name, mixed $value): self
    {
        $this->params[$name] = $value;
        return $this;
    }

    /**
     * Get SQL query
     */
    public function getSQL(): string
    {
        $sql = "SELECT " . ($this->select ?: '*') . " FROM {$this->from}";
        
        if (!empty($this->joins)) {
            $sql .= " " . implode(' ', $this->joins);
        }
        
        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
        }
        
        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }
        
        if ($this->limit !== null) {
            $sql .= " LIMIT :limit";
            $this->params['limit'] = $this->limit;
        }
        
        if ($this->offset !== null) {
            $sql .= " OFFSET :offset";
            $this->params['offset'] = $this->offset;
        }
        
        return $sql;
    }

    /**
     * Get results as array
     */
    public function getResult(): array
    {
        $sql = $this->getSQL();
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get single result
     */
    public function getOneOrNullResult(): ?array
    {
        $results = $this->getResult();
        return $results[0] ?? null;
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
}

