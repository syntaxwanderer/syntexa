<?php

declare(strict_types=1);

namespace Syntexa\Tests\Examples\Fixtures\Post;

use Syntexa\Orm\Repository\DomainRepository;

class Repository extends DomainRepository
{
    public function __construct(\Syntexa\Orm\Entity\EntityManager $em)
    {
        // In DDD approach, pass domain class - repository will resolve storage automatically
        parent::__construct($em, Domain::class);
    }
}

