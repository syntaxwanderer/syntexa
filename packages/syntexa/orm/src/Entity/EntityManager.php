<?php

declare(strict_types=1);

namespace Syntexa\Orm\Entity;

use PDO;
use Syntexa\Orm\Connection\ConnectionPool;
use Syntexa\Orm\Metadata\EntityMetadata;
use Syntexa\Orm\Metadata\EntityMetadataFactory;
use Syntexa\Orm\Query\QueryBuilder;
use Syntexa\Orm\Attributes\TimestampColumn;
use Syntexa\Orm\Mapping\DomainContext;
use Syntexa\Orm\Mapping\DomainMapperInterface;
use Syntexa\Orm\Mapping\DefaultDomainMapper;
use Syntexa\Orm\Blockchain\BlockchainConfig;
use Syntexa\Orm\Blockchain\BlockchainFieldExtractor;
use Syntexa\Orm\Blockchain\BlockchainPublisher;
use Syntexa\Orm\Blockchain\BlockchainTransaction;
use Syntexa\Orm\Blockchain\TransactionIdGenerator;

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
    /** @var array<class-string, EntityMetadata> */
    private array $entityMetadata = [];
    private DomainContext $domainContext;
    private ?DomainMapperInterface $defaultMapper = null;

    // Blockchain integration (optional, enabled via config)
    private ?BlockchainConfig $blockchainConfig = null;
    private ?BlockchainFieldExtractor $blockchainFieldExtractor = null;
    private ?TransactionIdGenerator $transactionIdGenerator = null;
    private ?BlockchainPublisher $blockchainPublisher = null;

    /**
     * @param PDO|object|null $connection Connection (PDO in CLI, PDOProxy in Swoole)
     */
    public function __construct($connection = null, ?DomainContext $domainContext = null)
    {
        $this->connection = $connection ?? ConnectionPool::get();
        $this->domainContext = $domainContext ?? new DomainContext();

        // Lazy blockchain config (safe, read-only)
        $this->blockchainConfig = BlockchainConfig::fromEnv();

        if ($this->blockchainConfig->enabled) {
            $this->blockchainFieldExtractor = new BlockchainFieldExtractor();
            $this->transactionIdGenerator = new TransactionIdGenerator();
            $storage = null;
            if ($this->blockchainConfig->hasBlockchainDb()) {
                $storage = new \Syntexa\Orm\Blockchain\BlockchainStorage($this->blockchainConfig);
            }
            $this->blockchainPublisher = new BlockchainPublisher($this->blockchainConfig, $storage);
        }
    }

    /**
     * Find entity by ID
     * 
     * @param string $entityClass Storage entity class or domain entity class
     */
    public function find(string $entityClass, int $id): ?object
    {
        // Resolve storage entity class if domain class is provided
        $storageClass = $this->resolveStorageClass($entityClass);
        $metadata = $this->getEntityMetadata($storageClass);
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
        
        return $this->mapDomainIfNeeded($metadata, $this->hydrateStorageEntity($metadata, $data));
    }

    /**
     * Find entities by criteria
     * 
     * @param string $entityClass Storage entity class or domain entity class
     */
    public function findBy(string $entityClass, array $criteria = [], ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        // Resolve storage entity class if domain class is provided
        $storageClass = $this->resolveStorageClass($entityClass);
        $metadata = $this->getEntityMetadata($storageClass);
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
            $entities[] = $this->mapDomainIfNeeded($metadata, $this->hydrateStorageEntity($metadata, $data));
        }
        
        return $entities;
    }

    /**
     * Find one entity by criteria
     * 
     * @param string $entityClass Storage entity class or domain entity class
     */
    public function findOneBy(string $entityClass, array $criteria): ?object
    {
        $results = $this->findBy($entityClass, $criteria, null, 1);
        return $results[0] ?? null;
    }

    /**
     * Resolve storage entity class from domain or storage class
     * 
     * @param string $entityClass Domain or storage entity class
     * @return string Storage entity class
     * @throws \RuntimeException If storage entity is passed directly (must use domain class or repository)
     */
    private function resolveStorageClass(string $entityClass): string
    {
        try {
            // Try to get metadata - if it works, it's a storage entity
            $metadata = $this->getEntityMetadata($entityClass);
            
            // If it's a storage entity with domainClass configured, reject it
            // Storage entities should not be used directly - use domain class instead
            if ($metadata->domainClass !== null) {
                throw new \RuntimeException(
                    "Storage entity '{$entityClass}' cannot be used directly. " .
                    "Use domain class '{$metadata->domainClass}' instead, or use a repository. " .
                    "This ensures proper separation between domain and infrastructure layers."
                );
            }
            
            // If it's a storage entity without domainClass, it's legacy or misconfigured
            // Still reject it to enforce DDD pattern
            throw new \RuntimeException(
                "Storage entity '{$entityClass}' cannot be used directly. " .
                "Use a repository or configure domainClass in #[AsEntity] attribute. " .
                "This ensures proper separation between domain and infrastructure layers."
            );
        } catch (\RuntimeException $e) {
            // If it's our validation exception, re-throw it
            if (str_contains($e->getMessage(), 'cannot be used directly')) {
                throw $e;
            }
            // Otherwise, it's not a storage entity, try to resolve from domain
        }

        $storageClass = EntityMetadataFactory::resolveEntityClassForDomain($entityClass);
        if ($storageClass === null) {
            throw new \RuntimeException("Cannot resolve storage entity for class {$entityClass}. Make sure it has #[AsEntity] attribute with domainClass parameter, or is a valid domain class.");
        }

        return $storageClass;
    }

    /**
     * Save entity (insert or update) - immediately writes to database
     * 
     * Replaces the old persist() + flush() pattern. This method writes
     * to the database immediately, making the API simpler and more intuitive.
     * 
     * @param object $entity Domain or storage entity
     * @return object Saved entity (with ID if new)
     */
    public function save(object $entity): object
    {
        $storage = $this->ensureStorageEntity($entity);
        $metadata = $this->getEntityMetadata(get_class($storage));

        // Perform database write first (immediate write)
        $this->saveEntity($storage);

        // After successful DB write, publish blockchain transaction (async, non-blocking)
        $this->publishBlockchainTransaction($storage, $metadata, $entity);

        return $this->mapDomainIfNeeded($metadata, $storage);
    }

    /**
     * Update existing entity - immediately writes to database
     * 
     * @param object $entity Domain or storage entity (must have ID)
     * @return object Updated entity
     * @throws \RuntimeException If entity doesn't have ID
     */
    public function update(object $entity): object
    {
        $storage = $this->ensureStorageEntity($entity);
        $metadata = $this->getEntityMetadata(get_class($storage));
        $identifier = $metadata->identifier;
        
        if ($identifier === null) {
            throw new \RuntimeException("Cannot update entity without identifier metadata.");
        }
        
        $idValue = $identifier->getValue($storage);
        if ($idValue === null) {
            throw new \RuntimeException('Cannot update entity without ID. Use save() for new entities.');
        }
        
        $this->saveEntity($storage);
        return $this->mapDomainIfNeeded($metadata, $storage);
    }

    /**
     * Delete entity - immediately removes from database
     * 
     * @param object $entity Domain or storage entity
     * @throws \RuntimeException If entity doesn't have ID
     */
    public function delete(object $entity): void
    {
        $storage = $this->ensureStorageEntity($entity);
        $entityClass = get_class($storage);
        $metadata = $this->getEntityMetadata($entityClass);
        $table = $metadata->tableName;

        $identifier = $metadata->identifier;
        if ($identifier === null) {
            throw new \RuntimeException("Cannot remove entity {$entityClass} without identifier.");
        }

        $idValue = $identifier->getValue($storage);
        if ($idValue === null) {
            throw new \RuntimeException('Cannot remove entity without ID');
        }

        // Create snapshot hash before deletion (for blockchain delete event)
        $snapshotHash = $this->createSnapshotHash($storage);

        $columnName = $identifier->columnName;
        $stmt = $this->connection->prepare("DELETE FROM {$table} WHERE {$columnName} = :id");
        $stmt->execute(['id' => $identifier->convertToDatabaseValue($idValue)]);

        // Publish delete transaction to blockchain (after DB delete)
        $this->publishBlockchainDeleteTransaction($storage, $metadata, (int) $idValue, $snapshotHash);
    }

    /**
     * Create query builder
     */
    public function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->connection, $this);
    }

    /**
     * Save entity asynchronously (Swoole only)
     * 
     * In Swoole environment, database operations through connection pool
     * are already non-blocking. This method creates a coroutine for the operation.
     * 
     * @param object $entity Domain or storage entity
     * @return \Generator Yields the saved entity (with ID if new)
     * @throws \RuntimeException If Swoole is not available
     */
    public function saveAsync(object $entity): \Generator
    {
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Swoole extension is required for async operations');
        }

        // In Swoole, operations through connection pool are already async
        // We just yield to allow other coroutines to run
        yield;
        return $this->save($entity);
    }

    /**
     * Update entity asynchronously (Swoole only)
     * 
     * @param object $entity Domain or storage entity (must have ID)
     * @return \Generator Yields the updated entity
     * @throws \RuntimeException If Swoole is not available or entity doesn't have ID
     */
    public function updateAsync(object $entity): \Generator
    {
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Swoole extension is required for async operations');
        }

        yield;
        return $this->update($entity);
    }

    /**
     * Delete entity asynchronously (Swoole only)
     * 
     * @param object $entity Domain or storage entity
     * @return \Generator Yields void when complete
     * @throws \RuntimeException If Swoole is not available or entity doesn't have ID
     */
    public function deleteAsync(object $entity): \Generator
    {
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Swoole extension is required for async operations');
        }

        yield;
        $this->delete($entity);
    }

    /**
     * Get entity metadata (table name, etc.)
     * 
     * @param string $entityClass Storage entity class or domain entity class
     * @return EntityMetadata
     */
    public function getEntityMetadata(string $entityClass): EntityMetadata
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
    private function hydrateStorageEntity(EntityMetadata $metadata, array $data): object
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

    private function mapDomainIfNeeded(EntityMetadata $metadata, object $storage): object
    {
        if ($metadata->domainClass === null) {
            return $storage;
        }

        $mapper = $this->getMapper($metadata);
        $domain = $mapper->toDomain($storage, $metadata, $this->domainContext);

        return $domain;
    }

    private function getMapper(EntityMetadata $metadata): DomainMapperInterface
    {
        if ($metadata->mapperClass) {
            return new ($metadata->mapperClass)();
        }

        if ($this->defaultMapper === null) {
            $this->defaultMapper = new DefaultDomainMapper($this);
        }

        return $this->defaultMapper;
    }

    private function ensureStorageEntity(object $entity): object
    {
        $entityClass = get_class($entity);
        try {
            // If it's already a storage entity, metadata will load fine.
            $metadata = $this->getEntityMetadata($entityClass);
            
            // Reject storage entities - they should not be passed directly
            // This ensures proper separation between domain and infrastructure layers
            throw new \RuntimeException(
                "Storage entity '{$entityClass}' cannot be used directly. " .
                ($metadata->domainClass 
                    ? "Use domain class '{$metadata->domainClass}' instead, or use a repository."
                    : "Use a repository or configure domainClass in #[AsEntity] attribute."
                ) . " This ensures proper separation between domain and infrastructure layers."
            );
        } catch (\RuntimeException $e) {
            // If it's our validation exception, re-throw it
            if (str_contains($e->getMessage(), 'cannot be used directly')) {
                throw $e;
            }
            // Otherwise, it's not a storage entity; try mapping from domain to storage.
        }

        $storageClass = EntityMetadataFactory::resolveEntityClassForDomain($entityClass);

        if ($storageClass === null) {
            throw new \RuntimeException("Cannot resolve storage entity for domain class {$entityClass}. Make sure #[AsEntity] attribute has domainClass parameter configured.");
        }

        $storageMetadata = $this->getEntityMetadata($storageClass);
        $mapper = $this->getMapper($storageMetadata);

        // Reuse existing storage if domain was previously mapped
        $domainId = spl_object_id($entity);
        if (isset($this->domainContext->domainToStorage[$domainId])) {
            $storage = $this->domainContext->domainToStorage[$domainId];
        } else {
            $storage = new $storageClass();
        }

        return $mapper->toStorage($entity, $storage, $storageMetadata, $this->domainContext);
    }

    /**
     * Publish blockchain transaction for save/update
     */
    private function publishBlockchainTransaction(object $storage, EntityMetadata $metadata, object $domainEntity): void
    {
        if (!$this->blockchainConfig?->enabled || !$this->blockchainPublisher || !$this->blockchainFieldExtractor) {
            return;
        }

        // Extract identifier
        $identifier = $metadata->identifier;
        $idValue = $identifier?->getValue($storage);
        if ($idValue === null) {
            // New entity without ID (should not happen after saveEntity for generated IDs)
            return;
        }

        // Extract blockchain fields
        $fields = $this->blockchainFieldExtractor->extractFields($storage, $metadata);
        if (empty($fields)) {
            // Nothing to log
            return;
        }

        $timestamp = new \DateTimeImmutable();
        $nodeId = $this->blockchainConfig->nodeId ?? 'node-unknown';

        // Generate transaction ID
        if (!$this->transactionIdGenerator) {
            $this->transactionIdGenerator = new TransactionIdGenerator();
        }

        $operation = $metadata->identifier && $metadata->identifier->getValue($storage) ? 'save' : 'save';

        $transactionId = $this->transactionIdGenerator->generate(
            $nodeId,
            $metadata->className,
            (int) $idValue,
            $operation,
            $fields,
            $timestamp
        );

        $transaction = new BlockchainTransaction(
            transactionId: $transactionId,
            nodeId: $nodeId,
            entityClass: $metadata->className,
            entityId: (int) $idValue,
            operation: $operation,
            fields: $fields,
            timestamp: $timestamp,
            nonce: base64_encode(random_bytes(32)),
            signature: null,
            keyVersion: null,
            publicKey: null,
            snapshotHash: null,
            reason: null,
        );

        $this->blockchainPublisher->publish($transaction);
    }

    /**
     * Publish blockchain delete transaction
     */
    private function publishBlockchainDeleteTransaction(object $storage, EntityMetadata $metadata, int $id, string $snapshotHash): void
    {
        if (!$this->blockchainConfig?->enabled || !$this->blockchainPublisher) {
            return;
        }

        $timestamp = new \DateTimeImmutable();
        $nodeId = $this->blockchainConfig->nodeId ?? 'node-unknown';

        if (!$this->transactionIdGenerator) {
            $this->transactionIdGenerator = new TransactionIdGenerator();
        }

        $fields = [
            'entity_id' => $id,
            'entity_class' => $metadata->className,
            'snapshot_hash' => $snapshotHash,
        ];

        $transactionId = $this->transactionIdGenerator->generate(
            $nodeId,
            $metadata->className,
            $id,
            'delete',
            $fields,
            $timestamp
        );

        $transaction = new BlockchainTransaction(
            transactionId: $transactionId,
            nodeId: $nodeId,
            entityClass: $metadata->className,
            entityId: $id,
            operation: 'delete',
            fields: $fields,
            timestamp: $timestamp,
            nonce: base64_encode(random_bytes(32)),
            signature: null,
            keyVersion: null,
            publicKey: null,
            snapshotHash: $snapshotHash,
            reason: null,
        );

        $this->blockchainPublisher->publish($transaction);
    }

    /**
     * Create snapshot hash before delete
     */
    private function createSnapshotHash(object $storage): string
    {
        // For now, simple serialize-based snapshot
        return hash('sha256', serialize($storage));
    }
}

