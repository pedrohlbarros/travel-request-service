<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Messaging\Outbox;

use DateTimeInterface;
use DateTimeZone;

/**
 * CloudEvents 1.0 in structured mode: the whole event, attributes and data, is the message body.
 */
final readonly class CloudEvent
{
    public const string SPEC_VERSION = '1.0';

    public const string CONTENT_TYPE = 'application/cloudevents+json';

    public const string DATA_CONTENT_TYPE = 'application/json';

    private const string TIMEZONE = 'UTC';

    /**
     * @return array{
     *     specversion: string,
     *     id: string,
     *     source: string,
     *     type: string,
     *     subject: string,
     *     time: string,
     *     datacontenttype: string,
     *     data: array<string, mixed>,
     * }
     */
    public static function envelope(EventMessage $message, string $eventId, string $source): array
    {
        $utc = new DateTimeZone(self::TIMEZONE);
        $occurredAtUtc = $message->occurredAt->setTimezone($utc);

        return [
            'specversion' => self::SPEC_VERSION,
            'id' => $eventId,
            'source' => $source,
            'type' => $message->routingKey,
            'subject' => $message->subject,
            'time' => $occurredAtUtc->format(DateTimeInterface::ATOM),
            'datacontenttype' => self::DATA_CONTENT_TYPE,
            'data' => $message->eventData,
        ];
    }
}
