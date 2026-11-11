<?php

declare(strict_types=1);

namespace Interfaces\Http\TravelRequest\Resource;

use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\Output\UserReferenceOutput;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Interfaces\Http\Shared\Concurrency\EntityTag;
use Interfaces\Http\TravelRequest\Request\CalendarDate;

/**
 * @property TravelRequestOutput $resource
 */
final class TravelRequestResource extends JsonResource
{
    public function __construct(TravelRequestOutput $travelRequest)
    {
        parent::__construct($travelRequest);
    }

    /**
     * @return array{
     *     id: string,
     *     requester: array{id: string, name: string},
     *     destination: string,
     *     departure_date: string,
     *     return_date: string,
     *     status: 'requested'|'approved'|'cancelled',
     *     version: int,
     *     decided_by: array{id: string, name: string}|null,
     *     created_at: string,
     *     updated_at: string,
     * }
     */
    public function toArray(Request $request): array
    {
        $decidedBy = $this->resource->decidedBy;

        return [
            'id' => $this->resource->id,
            'requester' => $this->userOf(user: $this->resource->requester),
            'destination' => $this->resource->destination,
            'departure_date' => $this->resource->departureDate->format(CalendarDate::FORMAT),
            'return_date' => $this->resource->returnDate->format(CalendarDate::FORMAT),
            'status' => $this->resource->status->value,
            'version' => $this->resource->version,
            'decided_by' => $decidedBy === null ? null : $this->userOf(user: $decidedBy),
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $this->resource->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{id: string, name: string}
     */
    private function userOf(UserReferenceOutput $user): array
    {
        return ['id' => $user->id, 'name' => $user->name];
    }

    /**
     * A single request always carries its version as ETag, ready for the If-Match of the next write.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->header(EntityTag::HEADER, EntityTag::ofVersion(version: $this->resource->version));
    }
}
