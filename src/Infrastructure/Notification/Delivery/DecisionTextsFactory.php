<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Delivery;

use Domain\Notification\ValueObject\TravelRequestDecision;
use Illuminate\Contracts\Translation\Translator;

final readonly class DecisionTextsFactory
{
    private const string CALENDAR_DATE = 'd/m/Y';

    public function __construct(
        private Translator $translator,
        private string $locale,
    ) {}

    public function for(TravelRequestDecision $decision): DecisionTexts
    {
        $outcome = $decision->outcome->value;
        $replacements = [
            'name' => $decision->recipient->name,
            'destination' => $decision->destination,
            'departure' => $decision->departureDate->format(self::CALENDAR_DATE),
            'return' => $decision->returnDate->format(self::CALENDAR_DATE),
        ];

        return new DecisionTexts(
            locale: $this->locale,
            subject: $this->text(key: "notification.{$outcome}.subject", replacements: $replacements),
            greeting: $this->text(key: 'notification.greeting', replacements: $replacements),
            message: $this->text(key: "notification.{$outcome}.message", replacements: $replacements),
            salutation: $this->text(key: 'notification.salutation', replacements: $replacements),
        );
    }

    /**
     * @param array<string, string> $replacements
     */
    private function text(string $key, array $replacements): string
    {
        $text = $this->translator->get($key, $replacements, $this->locale);

        return \is_string($text) ? $text : $key;
    }
}
