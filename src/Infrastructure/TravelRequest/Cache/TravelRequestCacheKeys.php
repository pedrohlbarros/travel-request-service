<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest\Cache;

use Application\TravelRequest\Port\TravelRequestListCriteria;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;

/**
 * Lists are cached per scope generation: a write bumps the generation of every scope that can see the request,
 * so the old list keys are never read again and simply expire.
 */
final readonly class TravelRequestCacheKeys
{
    private const string DETAIL = 'travel_request:%s';

    private const string GENERATION = 'travel_requests:generation:%s';

    private const string LIST = 'travel_requests:list:%s:%d:%s';

    private const string OWNER_SCOPE = 'owner:%s';

    private const string ADMIN_SCOPE = 'admin';

    private const string CRITERIA_HASH = 'xxh128';

    public static function detail(TravelRequestId $id): string
    {
        return \sprintf(self::DETAIL, $id->toString());
    }

    public static function listGeneration(?UserId $requesterId): string
    {
        return \sprintf(self::GENERATION, self::scopeOf(requesterId: $requesterId));
    }

    public static function list(TravelRequestListCriteria $criteria, int $generation): string
    {
        $scope = self::scopeOf(requesterId: $criteria->requesterId);
        $criteriaHash = hash(self::CRITERIA_HASH, serialize($criteria));

        return \sprintf(
            self::LIST,
            $scope,
            $generation,
            $criteriaHash,
        );
    }

    /**
     * @return list<string> the generations of the requester's own lists and of the admin lists
     */
    public static function generationsSeeing(UserId $requesterId): array
    {
        return [self::listGeneration(requesterId: $requesterId), self::listGeneration(requesterId: null)];
    }

    private static function scopeOf(?UserId $requesterId): string
    {
        return $requesterId === null ? self::ADMIN_SCOPE : \sprintf(self::OWNER_SCOPE, $requesterId->toString());
    }
}
