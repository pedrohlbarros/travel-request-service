<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Console;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Shared\Messaging\Outbox\OutboxDomainEventPublisher;
use Tests\Support\LaravelTestCase;

final class OutboxStatusCommandTest extends LaravelTestCase
{
    use RefreshDatabase;

    public function test_CountsPendingAndPublishedMessages(): void
    {
        $this->storeRow(occurredAt: '2026-11-10 10:00:00', publishedAt: null);
        $this->storeRow(occurredAt: '2026-11-10 09:00:00', publishedAt: null);
        $this->storeRow(occurredAt: '2026-11-10 08:00:00', publishedAt: '2026-11-10 08:00:01');

        $this->command(command: 'outbox:status')
            ->expectsTable(['Pending', 'Published', 'Oldest pending (UTC)'], [[2, 1, '2026-11-10 09:00:00']])
            ->assertSuccessful();
    }

    public function test_AnEmptyOutboxHasNoOldestPending(): void
    {
        $this->command(command: 'outbox:status')
            ->expectsTable(['Pending', 'Published', 'Oldest pending (UTC)'], [[0, 0, '-']])
            ->assertSuccessful();
    }

    private function storeRow(string $occurredAt, ?string $publishedAt): void
    {
        $this->app->make(ConnectionInterface::class)
            ->table(OutboxDomainEventPublisher::TABLE)
            ->insert([
                'id' => random_bytes(16),
                'exchange' => 'any.exchange',
                'routing_key' => 'any.key',
                'payload' => '{}',
                'headers' => '{}',
                'occurred_at' => $occurredAt,
                'published_at' => $publishedAt,
            ]);
    }
}
