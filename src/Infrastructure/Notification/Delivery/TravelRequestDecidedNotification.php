<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Delivery;

use Domain\Notification\ValueObject\TravelRequestDecision;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TravelRequestDecidedNotification extends Notification
{
    public function __construct(
        public readonly TravelRequestDecision $decision,
        public readonly DecisionTexts $texts,
    ) {
        $this->id = $decision->eventId->value;
        $this->locale = $texts->locale;
    }

    public function toMail(): MailMessage
    {
        return new MailMessage()
            ->subject($this->texts->subject)
            ->greeting($this->texts->greeting)
            ->line($this->texts->message)
            ->salutation($this->texts->salutation);
    }
}
