<?php

declare(strict_types=1);

namespace Interfaces\Http\Notification\Resource;

use Application\Notification\Output\NotificationOutput;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property NotificationOutput $resource
 */
final class NotificationResource extends JsonResource
{
    public function __construct(NotificationOutput $notification)
    {
        parent::__construct($notification);
    }

    /**
     * @return array{
     *     id: string,
     *     type: string,
     *     message: string,
     *     data: array<string, mixed>,
     *     read_at: string|null,
     *     created_at: string,
     * }
     */
    public function toArray(Request $request): array
    {
        $notification = $this->resource;

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'message' => $notification->message,
            'data' => $notification->details,
            'read_at' => $notification->readAt?->format(DateTimeInterface::ATOM),
            'created_at' => $notification->createdAt->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * Wraps in "data" explicitly: the notification has a "data" field of its own, which makes Laravel take the
     * body as already wrapped.
     *
     * @param Request $request
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(['data' => $this->toArray(request: $request)]);
    }
}
