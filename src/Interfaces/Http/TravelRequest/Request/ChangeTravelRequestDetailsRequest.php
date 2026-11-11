<?php

declare(strict_types=1);

namespace Interfaces\Http\TravelRequest\Request;

use Application\TravelRequest\Input\ChangeTravelRequestDetailsInput;
use Domain\TravelRequest\ValueObject\Destination;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Partial update: only the fields sent are changed, and at least one must be sent.
 */
final class ChangeTravelRequestDetailsRequest extends FormRequest
{
    private const string AT_LEAST_ONE_OF = 'required_without_all:';

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $returnDateRules = [self::AT_LEAST_ONE_OF . 'destination,departure_date', 'date_format:' . CalendarDate::FORMAT];

        // Against the stored departure the domain decides, since the request does not carry it.
        if ($this->filled('departure_date')) {
            $returnDateRules[] = 'after_or_equal:departure_date';
        }

        return [
            'destination' => [
                self::AT_LEAST_ONE_OF . 'departure_date,return_date',
                'string',
                'min:' . Destination::SHORTEST_NAME,
                'max:' . Destination::LONGEST_NAME,
            ],
            'departure_date' => [self::AT_LEAST_ONE_OF . 'destination,return_date', 'date_format:' . CalendarDate::FORMAT],
            'return_date' => $returnDateRules,
        ];
    }

    public function toInput(string $travelRequestId, string $editorId, int $expectedVersion): ChangeTravelRequestDetailsInput
    {
        return new ChangeTravelRequestDetailsInput(
            travelRequestId: $travelRequestId,
            editorId: $editorId,
            expectedVersion: $expectedVersion,
            destination: $this->destination(),
            departureDate: CalendarDate::optional(request: $this, field: 'departure_date'),
            returnDate: CalendarDate::optional(request: $this, field: 'return_date'),
        );
    }

    private function destination(): ?string
    {
        if (! $this->has('destination')) {
            return null;
        }

        return $this->string('destination')
            ->toString();
    }
}
