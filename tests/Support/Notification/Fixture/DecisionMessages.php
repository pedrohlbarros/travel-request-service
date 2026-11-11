<?php

declare(strict_types=1);

namespace Tests\Support\Notification\Fixture;

use Tests\Support\Notification\Fixture\DecisionMother as Mother;

/**
 * Decision messages as the travel request outbox publishes them.
 */
final class DecisionMessages
{
    public const string APPROVED = 'travel_request.approved.v1';

    public const string CANCELLED = 'travel_request.cancelled.v1';

    /**
     * @param array<string, mixed> $overrides top-level attributes to replace
     * @param array<string, mixed> $dataOverrides data fields to replace
     *
     * @return array<string, mixed>
     */
    public static function event(
        string $type = self::APPROVED,
        string $eventId = Mother::EVENT_ID,
        array $overrides = [],
        array $dataOverrides = [],
    ): array {
        $deciderField = $type === self::CANCELLED ? 'cancelled_by' : 'approved_by';
        $decision = [
            'travel_request_id' => Mother::TRAVEL_REQUEST_ID,
            'requester' => ['id' => Mother::REQUESTER_ID, 'name' => Mother::REQUESTER_NAME, 'email' => Mother::REQUESTER_EMAIL],
            'destination' => Mother::DESTINATION,
            'departure_date' => Mother::DEPARTURE_DATE,
            'return_date' => Mother::RETURN_DATE,
            'status' => $type === self::CANCELLED ? 'cancelled' : 'approved',
            'version' => 2,
            $deciderField => Mother::ADMIN_ID,
        ];

        return [
            'specversion' => '1.0',
            'id' => $eventId,
            'source' => '/travel-request-service',
            'type' => $type,
            'subject' => Mother::TRAVEL_REQUEST_ID,
            'time' => Mother::DECIDED_AT,
            'datacontenttype' => 'application/json',
            'data' => [...$decision, ...$dataOverrides],
            ...$overrides,
        ];
    }

    /**
     * @param array<string, mixed> $event
     */
    public static function body(array $event): string
    {
        return json_encode($event, JSON_THROW_ON_ERROR);
    }
}
