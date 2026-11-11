<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Delivery;

use Application\Notification\Port\DecisionNotifier;
use Domain\Notification\ValueObject\TravelRequestDecision;

final readonly class LaravelDecisionNotifier implements DecisionNotifier
{
    public const array DURABLE_CHANNELS = [NotificationsTableChannel::class, 'mail'];

    public function __construct(
        private DecisionTextsFactory $texts,
    ) {}

    public function notify(TravelRequestDecision $decision): void
    {
        $notifiable = new NotifiableRecipient(recipient: $decision->recipient);
        $texts = $this->texts->for(decision: $decision);
        $notification = new TravelRequestDecidedNotification(decision: $decision, texts: $texts);

        $notifiable->notifyNow(instance: $notification, channels: self::DURABLE_CHANNELS);
    }
}
