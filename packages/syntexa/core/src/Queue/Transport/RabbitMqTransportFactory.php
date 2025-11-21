<?php

declare(strict_types=1);

namespace Syntexa\Core\Queue\Transport;

use Syntexa\Core\Config\EnvValueResolver;
use Syntexa\Core\Queue\QueueTransportFactoryInterface;
use Syntexa\Core\Queue\QueueTransportInterface;

class RabbitMqTransportFactory implements QueueTransportFactoryInterface
{
    public function create(): QueueTransportInterface
    {
        $host = EnvValueResolver::resolve('env::RABBITMQ_HOST::127.0.0.1');
        $port = (int) EnvValueResolver::resolve('env::RABBITMQ_PORT::5672');
        $user = EnvValueResolver::resolve('env::RABBITMQ_USER::guest');
        $pass = EnvValueResolver::resolve('env::RABBITMQ_PASSWORD::guest');
        $vhost = EnvValueResolver::resolve('env::RABBITMQ_VHOST::/');

        return new RabbitMqTransport(
            host: $host,
            port: $port,
            user: $user,
            password: $pass,
            vhost: $vhost,
        );
    }
}

