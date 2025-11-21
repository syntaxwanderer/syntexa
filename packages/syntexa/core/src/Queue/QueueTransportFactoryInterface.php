<?php

declare(strict_types=1);

namespace Syntexa\Core\Queue;

interface QueueTransportFactoryInterface
{
    public function create(): QueueTransportInterface;
}

