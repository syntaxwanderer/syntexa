<?php

declare(strict_types=1);

namespace Syntexa\Orm\Repository;

use Syntexa\Orm\Entity\EntityManager;
use Syntexa\Orm\Query\QueryBuilder;

/**
 * Mapper-aware base repository: domain in/out, storage under the hood.
 */
class DomainRepository
{
    public function __construct(
        protected EntityManager $em,
        protected string $entityClass
    ) {
    }

    public function find(int $id): ?object
    {
        return $this->em->find($this->entityClass, $id);
    }

    public function findOneBy(array $criteria): ?object
    {
        return $this->em->findOneBy($this->entityClass, $criteria);
    }

    public function findBy(array $criteria = [], ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return $this->em->findBy($this->entityClass, $criteria, $orderBy, $limit, $offset);
    }

    public function save(object $domain): void
    {
        $this->em->persist($domain);
    }

    public function remove(object $domain): void
    {
        $this->em->remove($domain);
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function createQueryBuilder(string $alias = 'e'): QueryBuilder
    {
        return $this->em->createQueryBuilder()->from($this->entityClass, $alias);
    }
}

