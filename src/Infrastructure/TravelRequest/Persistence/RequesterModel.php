<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * The users table as travel requests see it: read only, and only the id and the name. Identity owns users and
 * is the only one writing them, so this context relates to them without depending on Identity's code.
 *
 * Ids stay as their raw 16 bytes, so relations compare BINARY(16) with BINARY(16) on both sides.
 *
 * @property string $id
 * @property string $name
 */
final class RequesterModel extends Model
{
    public const array COLUMNS = ['id', 'name'];

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'users';

    protected $keyType = 'string';

    /**
     * @return HasMany<TravelRequestModel, $this>
     */
    public function travelRequests(): HasMany
    {
        return $this->hasMany(TravelRequestModel::class, 'requester_id');
    }

    protected static function booted(): void
    {
        $refuseWrite = static function (): never {
            throw new LogicException('Users are written by Identity; travel requests only read them.');
        };

        static::saving($refuseWrite);
        static::deleting($refuseWrite);
    }
}
