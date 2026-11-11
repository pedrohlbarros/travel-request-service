<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Persistence;

use Application\Shared\Port\Transaction;
use Closure;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseTransaction implements Transaction
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function run(Closure $work): void
    {
        $this->connection->transaction($work);
    }
}
