<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest\Messaging;

use Domain\Shared\Event\DomainEvent;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\Event\TravelRequestApproved;
use Domain\TravelRequest\Event\TravelRequestCancelled;
use Domain\TravelRequest\ValueObject\UserId;
use Infrastructure\Shared\Messaging\Outbox\EventMessage;
use Infrastructure\Shared\Messaging\Outbox\EventMessageMapper;
use Infrastructure\Shared\Persistence\BinaryUuid;
use Infrastructure\TravelRequest\Persistence\RequesterModel;

/**
 * Fat events: the requester's name and e-mail travel with the decision, so consumers never ask Identity.
 */
final readonly class TravelRequestEventMessages implements EventMessageMapper
{
    public const string APPROVED = 'travel_request.approved.v1';

    public const string CANCELLED = 'travel_request.cancelled.v1';

    private const string CALENDAR_DATE = 'Y-m-d';

    public function __construct(
        private string $exchange,
    ) {}

    public function supports(DomainEvent $event): bool
    {
        return $event instanceof TravelRequestApproved || $event instanceof TravelRequestCancelled;
    }

    public function toMessage(DomainEvent $event): EventMessage
    {
        if ($event instanceof TravelRequestApproved) {
            return $this->decision(
                routingKey: self::APPROVED,
                decision: new TravelRequestDecision(
                    travelRequestId: $event->travelRequestId,
                    requesterId: $event->requesterId,
                    destination: $event->destination,
                    period: $event->period,
                    status: TravelRequestStatus::Approved,
                    version: $event->version,
                    deciderField: 'approved_by',
                    deciderId: $event->approvedBy,
                    occurredAt: $event->occurredAt,
                ),
            );
        }

        \assert($event instanceof TravelRequestCancelled);

        return $this->decision(
            routingKey: self::CANCELLED,
            decision: new TravelRequestDecision(
                travelRequestId: $event->travelRequestId,
                requesterId: $event->requesterId,
                destination: $event->destination,
                period: $event->period,
                status: TravelRequestStatus::Cancelled,
                version: $event->version,
                deciderField: 'cancelled_by',
                deciderId: $event->cancelledBy,
                occurredAt: $event->occurredAt,
            ),
        );
    }

    private function decision(string $routingKey, TravelRequestDecision $decision): EventMessage
    {
        $travelRequestId = $decision->travelRequestId->toString();

        return new EventMessage(
            exchange: $this->exchange,
            routingKey: $routingKey,
            subject: $travelRequestId,
            eventData: [
                'travel_request_id' => $travelRequestId,
                'requester' => self::requester(requesterId: $decision->requesterId),
                'destination' => $decision->destination->name,
                'departure_date' => $decision->period->departureDate->format(self::CALENDAR_DATE),
                'return_date' => $decision->period->returnDate->format(self::CALENDAR_DATE),
                'status' => $decision->status->value,
                'version' => $decision->version,
                $decision->deciderField => $decision->deciderId->toString(),
            ],
            occurredAt: $decision->occurredAt,
        );
    }

    /**
     * @return array{id: string, name: string, email: string}
     */
    private static function requester(UserId $requesterId): array
    {
        $requesterIdBytes = BinaryUuid::toBytes(uuid: $requesterId->toString());
        $requester = RequesterModel::query()
            ->whereKey($requesterIdBytes)
            ->first(RequesterModel::EVENT_COLUMNS);

        \assert($requester instanceof RequesterModel);

        return ['id' => $requesterId->toString(), 'name' => $requester->name, 'email' => $requester->email];
    }
}
