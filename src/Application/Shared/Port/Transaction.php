<?php

declare(strict_types=1);

namespace Application\Shared\Port;

use Closure;

interface Transaction
{
    /**
     * Runs the work atomically: everything it writes is kept, or nothing is when it throws.
     *
     * @param Closure(): void $work
     */
    public function run(Closure $work): void;
}
