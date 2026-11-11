<?php

declare(strict_types=1);

namespace Interfaces\Http\TravelRequest\Request;

use Application\TravelRequest\Input\ListTravelRequestsInput;
use Application\TravelRequest\Input\TravelRequestFilters;
use Closure;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\ValueObject\Destination;
use Illuminate\Foundation\Http\FormRequest;

final class ListTravelRequestsRequest extends FormRequest
{
    public const int DEFAULT_PER_PAGE = 20;

    public const int MAX_PER_PAGE = 100;

    private const string STATUS_SEPARATOR = ',';

    /**
     * @return array<string, list<string|Closure(string, mixed, Closure(string): void): void>>
     */
    public function rules(): array
    {
        $calendarDate = 'date_format:' . CalendarDate::FORMAT;

        return [
            'status' => ['sometimes', 'string', $this->listsOnlyKnownStatuses(...)],
            'destination' => ['sometimes', 'string', 'max:' . Destination::LONGEST_NAME],
            'created_from' => ['sometimes', $calendarDate],
            'created_to' => ['sometimes', $calendarDate, ...$this->notBefore(otherField: 'created_from')],
            'travel_from' => ['sometimes', $calendarDate],
            'travel_to' => ['sometimes', $calendarDate, ...$this->notBefore(otherField: 'travel_from')],
            'cursor' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'between:1,' . self::MAX_PER_PAGE],
        ];
    }

    public function toInput(string $viewerId, bool $isViewerAdmin): ListTravelRequestsInput
    {
        $filters = new TravelRequestFilters(
            statuses: $this->statuses(),
            destination: $this->optionalString(field: 'destination'),
            createdFrom: CalendarDate::optional(request: $this, field: 'created_from'),
            createdTo: CalendarDate::optional(request: $this, field: 'created_to'),
            travelFrom: CalendarDate::optional(request: $this, field: 'travel_from'),
            travelTo: CalendarDate::optional(request: $this, field: 'travel_to'),
        );
        $perPage = $this->integer('per_page', self::DEFAULT_PER_PAGE);

        return new ListTravelRequestsInput(
            viewerId: $viewerId,
            isViewerAdmin: $isViewerAdmin,
            filters: $filters,
            cursor: $this->optionalString(field: 'cursor'),
            perPage: max(1, $perPage),
        );
    }

    /**
     * @param Closure(string): void $fail
     */
    private function listsOnlyKnownStatuses(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (explode(self::STATUS_SEPARATOR, \is_string($value) ? $value : '') as $statusName) {
            if (TravelRequestStatus::tryFrom(value: trim($statusName)) === null) {
                $fail('The status must be a comma-separated list of requested, approved or cancelled.');

                return;
            }
        }
    }

    private function optionalString(string $field): ?string
    {
        if (! $this->has($field)) {
            return null;
        }

        return $this->string($field)
            ->toString();
    }

    /**
     * @return list<string>
     */
    private function notBefore(string $otherField): array
    {
        return $this->filled($otherField) ? ['after_or_equal:' . $otherField] : [];
    }

    /**
     * @return list<TravelRequestStatus>
     */
    private function statuses(): array
    {
        if (! $this->filled('status')) {
            return [];
        }

        $statusByName = [];

        $statusList = $this->string('status')
            ->toString();

        foreach (explode(self::STATUS_SEPARATOR, $statusList) as $statusName) {
            $status = TravelRequestStatus::from(value: trim($statusName));
            $statusByName[$status->value] = $status;
        }

        return array_values($statusByName);
    }
}
