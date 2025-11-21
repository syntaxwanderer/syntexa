<?php

declare(strict_types=1);

namespace Syntexa\Orm\Query;

use Swoole\Coroutine\PostgreSQL;

/**
 * Async Query Builder using Swoole Coroutine PostgreSQL
 * For asynchronous database queries
 */
class AsyncQueryBuilder
{
    private string $select = '';
    private string $from = '';
    private array $where = [];
    private array $params = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(
        private PostgreSQL $connection
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
     * From table
     */
    public function from(string $table, string $alias = 'e'): self
    {
        $this->from = "{$table} AS {$alias}";
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
        
        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
        }
        
        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }
        
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }
        
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }
        
        return $sql;
    }

    /**
     * Execute query asynchronously
     * Returns generator for coroutine
     */
    public function executeAsync(): \Generator
    {
        $sql = $this->getSQL();
        $result = $this->connection->query($sql);
        
        if ($result === false) {
            throw new \RuntimeException('Query failed: ' . $this->connection->error);
        }
        
        yield $result;
    }

    /**
     * Get results as array (async)
     */
    public function getResult(): array
    {
        $generator = $this->executeAsync();
        $result = $generator->current();
        
        if (!$result) {
            return [];
        }
        
        return $result;
    }

    /**
     * Get single result (async)
     */
    public function getOneOrNullResult(): ?array
    {
        $results = $this->getResult();
        return $results[0] ?? null;
    }
}

