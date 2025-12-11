<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Fixtures\Repository;

use Syntexa\Orm\Repository\DomainRepository;
use Syntexa\Tests\Examples\Fixtures\Storage\UserStorage;

class UserRepository extends DomainRepository
{
    public function __construct(\Syntexa\Orm\Entity\EntityManager $em)
    {
        parent::__construct($em, UserStorage::class);
    }
}

