<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest\Cache;

final readonly class TravelRequestCacheSettings
{
    /**
     * @param positive-int $detailSeconds
     * @param positive-int $listSeconds
     */
    public function __construct(
        public int $detailSeconds,
        public int $listSeconds,
    ) {}
}
