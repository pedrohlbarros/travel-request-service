<?php

declare(strict_types=1);

namespace Application\Notification\UseCase;

use Application\Notification\Input\NotifyRequesterOfDecisionInput;
use Application\Notification\Output\NotificationDelivery;
use Application\Notification\Port\DecisionBroadcaster;
use Application\Notification\Port\DecisionNotifier;
use Application\Notification\Port\ProcessedEvents;
use Application\Shared\Port\Transaction;
use Domain\Notification\Exception\InvalidDecision;
use Domain\Notification\ValueObject\Recipient;
use Domain\Notification\ValueObject\TravelRequestDecision;
use Domain\Shared\Exception\InvalidIdentifier;

final readonly class NotifyRequesterOfDecisionUseCase
{
    public function __construct(
        private ProcessedEvents $processedEvents,
        private DecisionNotifier $notifier,
        private DecisionBroadcaster $broadcaster,
        private Transaction $transaction,
    ) {}

    /**
     * The inbox entry, the stored notification and the e-mail succeed or fail together; the broadcast goes out
     * only after they hold.
     *
     * @throws InvalidIdentifier
     * @throws InvalidDecision
     */
    public function execute(NotifyRequesterOfDecisionInput $input): NotificationDelivery
    {
        $decision = self::decisionFrom(input: $input);
        $delivery = NotificationDelivery::AlreadyDelivered;

        $this->transaction->run(work: function () use ($decision, &$delivery): void {
            if (! $this->processedEvents->recordFirstDelivery(eventId: $decision->eventId)) {
                return;
            }

            $this->notifier->notify(decision: $decision);
            $delivery = NotificationDelivery::Delivered;
        });

        if ($delivery === NotificationDelivery::Delivered) {
            $this->broadcaster->broadcast(decision: $decision);
        }

        return $delivery;
    }

    /**
     * @throws InvalidIdentifier
     * @throws InvalidDecision
     */
    private static function decisionFrom(NotifyRequesterOfDecisionInput $input): TravelRequestDecision
    {
        $recipient = Recipient::of(id: $input->requesterId, name: $input->requesterName, email: $input->requesterEmail);

        return TravelRequestDecision::of(
            eventId: $input->eventId,
            travelRequestId: $input->travelRequestId,
            recipient: $recipient,
            destination: $input->destination,
            departureDate: $input->departureDate,
            returnDate: $input->returnDate,
            outcome: $input->outcome,
            decidedBy: $input->decidedBy,
            decidedAt: $input->decidedAt,
        );
    }
}
