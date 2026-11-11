<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest\Persistence;

use Carbon\CarbonImmutable;
use Domain\TravelRequest\ValueObject\UserId;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Infrastructure\Shared\Persistence\BinaryUuid;
use Infrastructure\Shared\Persistence\BinaryUuidCast;

/**
 * The user references keep their raw 16 bytes (see RequesterModel), so only the own id is cast.
 *
 * @property string $id
 * @property string $requester_id raw 16 bytes
 * @property string $destination
 * @property CarbonImmutable $departure_date
 * @property CarbonImmutable $return_date
 * @property string $status
 * @property int $version
 * @property ?string $decided_by raw 16 bytes
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read RequesterModel $requester
 * @property-read ?RequesterModel $decider
 */
final class TravelRequestModel extends Model
{
    /** Eager loads of both users, limited to the columns a response shows. */
    public const array WITH_USERS = ['requester:id,name', 'decider:id,name'];

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'travel_requests';

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * @return BelongsTo<RequesterModel, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(RequesterModel::class, 'requester_id');
    }

    /**
     * @return BelongsTo<RequesterModel, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(RequesterModel::class, 'decided_by');
    }

    /**
     * @param Builder<self> $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, UserId $requesterId): void
    {
        $requesterIdBytes = BinaryUuid::toBytes(uuid: $requesterId->toString());
        $query->where('requester_id', $requesterIdBytes);
    }

    protected function casts(): array
    {
        return [
            'id' => BinaryUuidCast::class,
            'departure_date' => 'immutable_date',
            'return_date' => 'immutable_date',
            'version' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
