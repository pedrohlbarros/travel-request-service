<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Persistence;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<string|null, string|null>
 */
final readonly class BinaryUuidCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return \is_string($value) ? BinaryUuid::fromBytes(bytes: $value) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return \is_string($value) ? BinaryUuid::toBytes(uuid: $value) : null;
    }
}
