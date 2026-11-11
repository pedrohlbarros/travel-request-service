<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Port;

use Application\Shared\Port\Transaction;
use Closure;

/**
 * Runs the work on the spot; in-memory fakes have nothing to roll back.
 */
final class ImmediateTransaction implements Transaction
{
    public private(set) int $completedRuns = 0;

    public function run(Closure $work): void
    {
        $work();
        $this->completedRuns++;
    }
}
