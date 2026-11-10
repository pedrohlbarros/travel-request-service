<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Messaging;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;

final readonly class RabbitMqConnectionFactory
{
    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $password,
        private string $vhost,
        private float $timeoutSeconds = 2.0,
    ) {}

    public function create(): AbstractConnection
    {
        $config = new AMQPConnectionConfig();
        $config->setHost($this->host);
        $config->setPort($this->port);
        $config->setUser($this->user);
        $config->setPassword($this->password);
        $config->setVhost($this->vhost);
        $config->setConnectionTimeout($this->timeoutSeconds);
        $config->setReadTimeout($this->timeoutSeconds);
        $config->setWriteTimeout($this->timeoutSeconds);

        return AMQPConnectionFactory::create($config);
    }
}
