<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Fixtures\User;

use Syntexa\Orm\Repository\DomainRepository;

class Repository extends DomainRepository
{
    public function __construct(\Syntexa\Orm\Entity\EntityManager $em)
    {
        parent::__construct($em, Storage::class);
    }
}

