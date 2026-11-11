<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Delivery;

use Application\Notification\Port\DecisionNotifier;
use Domain\Notification\ValueObject\TravelRequestDecision;
use Infrastructure\Shared\Observability\Telemetry;

final readonly class LaravelDecisionNotifier implements DecisionNotifier
{
    public const array DURABLE_CHANNELS = [NotificationsTableChannel::class, 'mail'];

    public function __construct(
        private DecisionTextsFactory $texts,
        private Telemetry $telemetry,
    ) {}

    public function notify(TravelRequestDecision $decision): void
    {
        $notifiable = new NotifiableRecipient(recipient: $decision->recipient);
        $texts = $this->texts->for(decision: $decision);
        $notification = new TravelRequestDecidedNotification(decision: $decision, texts: $texts);

        $this->telemetry->inSpan(
            name: 'notification.deliver',
            work: static function () use ($notifiable, $notification): void {
                $notifiable->notifyNow(instance: $notification, channels: self::DURABLE_CHANNELS);
            },
            attributes: ['notification.channels' => 'database,mail', 'messaging.message.id' => $decision->eventId->value],
        );
    }
}
