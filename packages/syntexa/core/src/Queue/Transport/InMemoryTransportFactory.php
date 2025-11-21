<?php

declare(strict_types=1);

namespace Syntexa\Core\Queue\Transport;

use Syntexa\Core\Queue\QueueTransportFactoryInterface;
use Syntexa\Core\Queue\QueueTransportInterface;

class InMemoryTransportFactory implements QueueTransportFactoryInterface
{
    public function create(): QueueTransportInterface
    {
        return new InMemoryTransport();
    }
}

