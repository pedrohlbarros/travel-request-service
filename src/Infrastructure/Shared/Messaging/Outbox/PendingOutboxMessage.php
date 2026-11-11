<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Messaging\Outbox;

use Infrastructure\Shared\Persistence\BinaryUuid;
use stdClass;

final readonly class PendingOutboxMessage
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $idBytes,
        public string $eventId,
        public string $exchange,
        public string $routingKey,
        public string $payload,
        public array $headers,
    ) {}

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self(
            idBytes: $this->idBytes,
            eventId: $this->eventId,
            exchange: $this->exchange,
            routingKey: $this->routingKey,
            payload: $this->payload,
            headers: $headers,
        );
    }

    /**
     * The row was written by the outbox itself, so its shape is known; the asserts only narrow types.
     */
    public static function fromRow(stdClass $row): self
    {
        \assert(\is_string($row->id) && \is_string($row->exchange) && \is_string($row->routing_key));
        \assert(\is_string($row->payload) && \is_string($row->headers));
        $storedHeaders = json_decode($row->headers, true, flags: JSON_THROW_ON_ERROR);
        \assert(\is_array($storedHeaders));
        $headers = [];

        foreach ($storedHeaders as $name => $value) {
            \assert(\is_string($name) && \is_string($value));
            $headers[$name] = $value;
        }

        $eventId = BinaryUuid::fromBytes(bytes: $row->id);

        return new self(
            idBytes: $row->id,
            eventId: $eventId,
            exchange: $row->exchange,
            routingKey: $row->routing_key,
            payload: $row->payload,
            headers: $headers,
        );
    }
}
