<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Shared\Persistence\DatabaseTransaction;
use RuntimeException;
use Tests\Support\LaravelTestCase;

final class DatabaseTransactionTest extends LaravelTestCase
{
    use RefreshDatabase;

    private const string TABLE = 'outbox_messages';

    public function test_KeepsWhatTheWorkWrote(): void
    {
        $transaction = new DatabaseTransaction(connection: $this->connection());

        $transaction->run(work: function (): void {
            $this->writeARow();
        });

        self::assertSame(1, $this->rowCount());
    }

    public function test_KeepsNothingWhenTheWorkFails(): void
    {
        $transaction = new DatabaseTransaction(connection: $this->connection());

        try {
            $transaction->run(work: function (): void {
                $this->writeARow();

                throw new RuntimeException('The second write failed.');
            });
        } catch (RuntimeException) {
        }

        self::assertSame(0, $this->rowCount());
    }

    private function writeARow(): void
    {
        $this->connection()
            ->table(self::TABLE)
            ->insert([
                'id' => random_bytes(16),
                'exchange' => 'any.exchange',
                'routing_key' => 'any.key',
                'payload' => '{}',
                'headers' => '{}',
                'occurred_at' => '2026-11-10 10:00:00',
            ]);
    }

    private function rowCount(): int
    {
        return $this->connection()
            ->table(self::TABLE)
            ->count();
    }

    private function connection(): ConnectionInterface
    {
        return $this->app->make(ConnectionInterface::class);
    }
}
