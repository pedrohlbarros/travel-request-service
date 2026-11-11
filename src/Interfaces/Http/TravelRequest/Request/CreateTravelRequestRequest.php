<?php

declare(strict_types=1);

namespace Interfaces\Http\TravelRequest\Request;

use Application\TravelRequest\Input\CreateTravelRequestInput;
use Domain\TravelRequest\ValueObject\Destination;
use Illuminate\Foundation\Http\FormRequest;

final class CreateTravelRequestRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'destination' => ['required', 'string', 'min:' . Destination::SHORTEST_NAME, 'max:' . Destination::LONGEST_NAME],
            'departure_date' => ['required', 'date_format:' . CalendarDate::FORMAT, 'after_or_equal:today'],
            'return_date' => ['required', 'date_format:' . CalendarDate::FORMAT, 'after_or_equal:departure_date'],
        ];
    }

    /**
     * The request always belongs to whoever creates it.
     */
    public function toInput(string $requesterId): CreateTravelRequestInput
    {
        return new CreateTravelRequestInput(
            requesterId: $requesterId,
            destination: $this->string('destination')
                ->toString(),
            departureDate: CalendarDate::required(request: $this, field: 'departure_date'),
            returnDate: CalendarDate::required(request: $this, field: 'return_date'),
        );
    }
}
