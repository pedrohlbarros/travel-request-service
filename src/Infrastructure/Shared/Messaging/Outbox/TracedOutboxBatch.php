<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Messaging\Outbox;

use Infrastructure\Shared\Observability\Telemetry;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use Throwable;

/**
 * One publish span per message, child of the span that stored it, and the message carries the publish span on
 * to its consumers. Without tracing the stored headers go out unchanged.
 */
final readonly class TracedOutboxBatch
{
    /**
     * @param list<PendingOutboxMessage> $messages
     * @param list<SpanInterface> $spans
     */
    private function __construct(
        public array $messages,
        private array $spans,
    ) {}

    /**
     * @param list<PendingOutboxMessage> $messages
     */
    public static function start(Telemetry $telemetry, array $messages): self
    {
        $tracedMessages = [];
        $spans = [];

        foreach ($messages as $message) {
            $span = $telemetry->tracer()
                ->spanBuilder('publish ' . $message->exchange)
                ->setSpanKind(SpanKind::KIND_PRODUCER)
                ->setParent($telemetry->contextFrom(carrier: $message->headers))
                ->setAttributes([
                    'messaging.system' => 'rabbitmq',
                    'messaging.operation.type' => 'send',
                    'messaging.destination.name' => $message->exchange,
                    'messaging.rabbitmq.destination.routing_key' => $message->routingKey,
                    'messaging.message.id' => $message->eventId,
                ])
                ->startSpan();
            $publishContext = $span->storeInContext(Context::getCurrent());
            $propagationHeaders = $telemetry->propagationHeaders(context: $publishContext);
            $tracedMessages[] = $message->withHeaders(headers: [...$message->headers, ...$propagationHeaders]);
            $spans[] = $span;
        }

        return new self(messages: $tracedMessages, spans: $spans);
    }

    public function succeed(): void
    {
        foreach ($this->spans as $span) {
            $span->end();
        }
    }

    public function fail(Throwable $failure): void
    {
        foreach ($this->spans as $span) {
            $span->recordException($failure);
            $span->setStatus(StatusCode::STATUS_ERROR, $failure->getMessage());
            $span->end();
        }
    }
}
