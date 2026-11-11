<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Delivery;

use Domain\Notification\ValueObject\TravelRequestDecision;

/**
 * What a stored or broadcast notification says about the decision, in the API's snake_case.
 */
final readonly class DecisionDetails
{
    private const string TYPE_PREFIX = 'travel_request.';

    private const string CALENDAR_DATE = 'Y-m-d';

    public static function typeOf(TravelRequestDecision $decision): string
    {
        return self::TYPE_PREFIX . $decision->outcome->value;
    }

    /**
     * @return array{
     *     travel_request_id: string,
     *     destination: string,
     *     departure_date: string,
     *     return_date: string,
     *     status: string,
     *     decided_by: string,
     * }
     */
    public static function of(TravelRequestDecision $decision): array
    {
        return [
            'travel_request_id' => $decision->travelRequestId->value,
            'destination' => $decision->destination,
            'departure_date' => $decision->departureDate->format(self::CALENDAR_DATE),
            'return_date' => $decision->returnDate->format(self::CALENDAR_DATE),
            'status' => $decision->outcome->value,
            'decided_by' => $decision->decidedBy->value,
        ];
    }
}
