<?php

declare(strict_types=1);

namespace Interfaces\Http\Identity\Resource;

use Application\Identity\Output\UserOutput;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property UserOutput $resource
 */
final class UserResource extends JsonResource
{
    public function __construct(UserOutput $user)
    {
        parent::__construct($user);
    }

    /**
     * @return array{id: string, name: string, email: string, role: string, created_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'role' => $this->resource->role->value,
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
