<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Persistence;

use Illuminate\Database\Eloquent\Builder;

/**
 * For the write side: a query that reads from the primary, never the replica, because what it reads decides
 * a write and the replica may not have the latest row yet.
 */
trait ReadsFromPrimary
{
    /**
     * @return Builder<self>
     */
    public static function onPrimary(): Builder
    {
        $query = self::query();
        $query->getQuery()
            ->useWritePdo();

        return $query;
    }
}
