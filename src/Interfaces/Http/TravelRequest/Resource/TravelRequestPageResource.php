<?php

declare(strict_types=1);

namespace Interfaces\Http\TravelRequest\Resource;

use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\Output\TravelRequestPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property TravelRequestPage $resource
 */
final class TravelRequestPageResource extends JsonResource
{
    private const string CURSOR_PARAMETER = 'cursor';

    public static $wrap;

    public function __construct(TravelRequestPage $page)
    {
        parent::__construct($page);
    }

    /**
     * @return array{
     *     data: list<array<string, mixed>>,
     *     meta: array{per_page: int, next_cursor: string|null, prev_cursor: string|null},
     *     links: array{next: string|null, prev: string|null},
     * }
     */
    public function toArray(Request $request): array
    {
        $page = $this->resource;
        $travelRequests = array_map(
            static fn(TravelRequestOutput $travelRequest): array => new TravelRequestResource(travelRequest: $travelRequest)
                ->toArray(request: $request),
            $page->travelRequests,
        );

        return [
            'data' => $travelRequests,
            'meta' => [
                'per_page' => $page->perPage,
                'next_cursor' => $page->nextCursor,
                'prev_cursor' => $page->previousCursor,
            ],
            'links' => [
                'next' => $this->linkTo(request: $request, cursor: $page->nextCursor),
                'prev' => $this->linkTo(request: $request, cursor: $page->previousCursor),
            ],
        ];
    }

    private function linkTo(Request $request, ?string $cursor): ?string
    {
        return $cursor === null ? null : $request->fullUrlWithQuery([self::CURSOR_PARAMETER => $cursor]);
    }
}
