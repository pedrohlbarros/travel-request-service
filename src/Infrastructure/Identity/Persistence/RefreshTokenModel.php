<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Infrastructure\Shared\Persistence\BinaryUuidCast;

/**
 * @property string $id
 * @property string $family_id
 * @property string $user_id
 * @property string $token_hash
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $rotated_at
 * @property CarbonImmutable|null $revoked_at
 */
final class RefreshTokenModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'refresh_tokens';

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => BinaryUuidCast::class,
            'family_id' => BinaryUuidCast::class,
            'user_id' => BinaryUuidCast::class,
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'rotated_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
