<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Messaging;

use Application\Notification\Input\NotifyRequesterOfDecisionInput;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;

/**
 * Reads the CloudEvents travel request decision. The message is the contract: nothing from the publishing
 * context's code is used here.
 */
final readonly class DecisionMessageParser
{
    public const array OUTCOME_BY_TYPE = [
        'travel_request.approved.v1' => ['outcome' => 'approved', 'decider' => 'approved_by'],
        'travel_request.cancelled.v1' => ['outcome' => 'cancelled', 'decider' => 'cancelled_by'],
    ];

    private const string CALENDAR_DATE = '!Y-m-d';

    private const string TIMEZONE = 'UTC';

    private const int JSON_DEPTH = 8;

    /**
     * @throws UnreadableDecisionMessage
     */
    public function parse(string $body): NotifyRequesterOfDecisionInput
    {
        $event = self::decoded(body: $body);
        $type = self::stringAt(values: $event, key: 'type');
        $outcome = self::OUTCOME_BY_TYPE[$type] ?? null;

        if ($outcome === null) {
            throw UnreadableDecisionMessage::because(reason: \sprintf('"%s" is not a decision type', $type));
        }

        $decision = self::arrayAt(values: $event, key: 'data');
        $requester = self::arrayAt(values: $decision, key: 'requester');

        return new NotifyRequesterOfDecisionInput(
            eventId: self::stringAt(values: $event, key: 'id'),
            travelRequestId: self::stringAt(values: $decision, key: 'travel_request_id'),
            requesterId: self::stringAt(values: $requester, key: 'id'),
            requesterName: self::stringAt(values: $requester, key: 'name'),
            requesterEmail: self::stringAt(values: $requester, key: 'email'),
            destination: self::stringAt(values: $decision, key: 'destination'),
            departureDate: self::dateAt(values: $decision, key: 'departure_date', format: self::CALENDAR_DATE),
            returnDate: self::dateAt(values: $decision, key: 'return_date', format: self::CALENDAR_DATE),
            outcome: $outcome['outcome'],
            decidedBy: self::stringAt(values: $decision, key: $outcome['decider']),
            decidedAt: self::dateAt(values: $event, key: 'time', format: DateTimeImmutable::ATOM),
        );
    }

    /**
     * @return array<mixed>
     *
     * @throws UnreadableDecisionMessage
     */
    private static function decoded(string $body): array
    {
        try {
            $event = json_decode(
                $body,
                true,
                self::JSON_DEPTH,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw UnreadableDecisionMessage::because(reason: 'the body is not JSON');
        }

        if (! \is_array($event)) {
            throw UnreadableDecisionMessage::because(reason: 'the body is not a JSON object');
        }

        return $event;
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<mixed>
     *
     * @throws UnreadableDecisionMessage
     */
    private static function arrayAt(array $values, string $key): array
    {
        $value = $values[$key] ?? null;

        if (! \is_array($value)) {
            throw UnreadableDecisionMessage::because(reason: \sprintf('"%s" is missing', $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $values
     *
     * @throws UnreadableDecisionMessage
     */
    private static function stringAt(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        if (! \is_string($value)) {
            throw UnreadableDecisionMessage::because(reason: \sprintf('"%s" is missing', $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $values
     *
     * @throws UnreadableDecisionMessage
     */
    private static function dateAt(array $values, string $key, string $format): DateTimeImmutable
    {
        $text = self::stringAt(values: $values, key: $key);
        $date = DateTimeImmutable::createFromFormat($format, $text, new DateTimeZone(self::TIMEZONE));

        if ($date === false) {
            throw UnreadableDecisionMessage::because(reason: \sprintf('"%s" is not a date', $key));
        }

        return $date;
    }
}
