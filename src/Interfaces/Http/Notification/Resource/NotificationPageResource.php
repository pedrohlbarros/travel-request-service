<?php

declare(strict_types=1);

namespace Interfaces\Http\Notification\Resource;

use Application\Notification\Output\NotificationOutput;
use Application\Notification\Output\NotificationPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property NotificationPage $resource
 */
final class NotificationPageResource extends JsonResource
{
    private const string CURSOR_PARAMETER = 'cursor';

    public static $wrap;

    public function __construct(NotificationPage $page)
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
        $notifications = array_map(
            static fn(NotificationOutput $notification): array => new NotificationResource(notification: $notification)
                ->toArray(request: $request),
            $page->notifications,
        );

        return [
            'data' => $notifications,
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
