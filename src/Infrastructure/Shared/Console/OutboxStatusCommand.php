<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Console;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Infrastructure\Shared\Messaging\Outbox\OutboxDomainEventPublisher;

final class OutboxStatusCommand extends Command
{
    protected $signature = 'outbox:status';

    protected $description = 'Show how many outbox messages are pending and published';

    public function handle(ConnectionInterface $connection): int
    {
        $pending = $connection->table(OutboxDomainEventPublisher::TABLE)
            ->whereNull('published_at');
        $pendingCount = (clone $pending)->count();
        $oldestPendingAt = (clone $pending)->min('occurred_at');
        $publishedCount = $connection->table(OutboxDomainEventPublisher::TABLE)
            ->whereNotNull('published_at')
            ->count();

        $this->table(
            ['Pending', 'Published', 'Oldest pending (UTC)'],
            [[$pendingCount, $publishedCount, \is_string($oldestPendingAt) ? $oldestPendingAt : '-']],
        );

        return self::SUCCESS;
    }
}
