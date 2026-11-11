<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Health;

use Illuminate\Database\ConnectionResolverInterface;
use stdClass;

/**
 * Reads SHOW REPLICA STATUS on the read replica, through its own connection so any process can ask.
 */
final readonly class MySqlReplicaStatus
{
    public const string CONNECTION = 'mysql_replica';

    private const string RUNNING = 'Yes';

    public function __construct(
        private ConnectionResolverInterface $connections,
    ) {}

    /**
     * @return ?ReplicaState null when the server is not set up as a replica
     */
    public function current(): ?ReplicaState
    {
        $status = $this->connections->connection(self::CONNECTION)
            ->selectOne('SHOW REPLICA STATUS');

        if (! $status instanceof stdClass) {
            return null;
        }

        $isReplicating = ($status->Replica_IO_Running ?? null) === self::RUNNING
            && ($status->Replica_SQL_Running ?? null) === self::RUNNING;
        $secondsBehind = $status->Seconds_Behind_Source ?? null;

        return new ReplicaState(
            isReplicating: $isReplicating,
            secondsBehindSource: is_numeric($secondsBehind) ? (int) $secondsBehind : null,
        );
    }
}
