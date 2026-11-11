<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Messaging;

use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Log\Context\Repository as Context;
use Infrastructure\Shared\Messaging\Outbox\OutboxDomainEventPublisher;
use Infrastructure\Shared\Messaging\RabbitMqConnectionFactory;
use Infrastructure\Shared\Observability\Telemetry;
use Infrastructure\Shared\Persistence\BinaryUuid;
use Tests\Support\Shared\Port\SequentialIdentifierGenerator;

trait UsesTheOutbox
{
    private const string SAMPLE_SOURCE = '/travel-request-service';

    private const float UNREACHABLE_TIMEOUT_SECONDS = 0.5;

    /**
     * Event ids follow SequentialIdentifierGenerator, starting at $firstSequence.
     *
     * @param list<string> $subjects
     */
    protected function storeSampleEvents(array $subjects, int $firstSequence = 1): void
    {
        $identifiers = new SequentialIdentifierGenerator();

        for ($skipped = 1; $skipped < $firstSequence; $skipped++) {
            $identifiers->uuid7();
        }

        $publisher = new OutboxDomainEventPublisher(
            connection: $this->app->make(ConnectionInterface::class),
            mappers: [new SampleEventMessages()],
            identifiers: $identifiers,
            context: $this->app->make(Context::class),
            telemetry: $this->app->make(Telemetry::class),
            source: self::SAMPLE_SOURCE,
        );

        foreach ($subjects as $subject) {
            $event = new SampleEvent(subject: $subject, occurredAt: new DateTimeImmutable());
            $publisher->publish($event);
        }
    }

    protected function rabbitMqOnPort(int $port): RabbitMqConnectionFactory
    {
        $config = $this->app->make(Config::class);

        return new RabbitMqConnectionFactory(
            host: $config->string('rabbitmq.host'),
            port: $port,
            user: $config->string('rabbitmq.user'),
            password: $config->string('rabbitmq.password'),
            vhost: $config->string('rabbitmq.vhost'),
            timeoutSeconds: self::UNREACHABLE_TIMEOUT_SECONDS,
        );
    }

    /**
     * @return list<string>
     */
    protected function pendingOutboxIds(): array
    {
        $pendingIds = [];
        $idBytes = $this->app->make(ConnectionInterface::class)
            ->table(OutboxDomainEventPublisher::TABLE)
            ->whereNull('published_at')
            ->orderBy('id')
            ->pluck('id');

        foreach ($idBytes as $bytes) {
            \assert(\is_string($bytes));
            $pendingIds[] = BinaryUuid::fromBytes(bytes: $bytes);
        }

        return $pendingIds;
    }

    /**
     * @return list<string>
     */
    protected function outboxColumn(string $column): array
    {
        $values = [];
        $storedValues = $this->app->make(ConnectionInterface::class)
            ->table(OutboxDomainEventPublisher::TABLE)
            ->orderBy('id')
            ->pluck($column);

        foreach ($storedValues as $storedValue) {
            \assert(\is_string($storedValue));
            $values[] = $storedValue;
        }

        return $values;
    }
}
