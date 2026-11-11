<?php

declare(strict_types=1);

namespace Tests\Support\Notification\Fixture;

use Illuminate\Database\ConnectionInterface;
use Infrastructure\Notification\Persistence\NotificationRows;
use Infrastructure\Shared\Persistence\BinaryUuid;

trait StoresNotifications
{
    protected function storeNotification(string $id, string $recipientId, ?string $readAt = null): void
    {
        $this->app->make(ConnectionInterface::class)
            ->table(NotificationRows::TABLE)
            ->insert([
                'id' => BinaryUuid::toBytes(uuid: $id),
                'notifiable_id' => BinaryUuid::toBytes(uuid: $recipientId),
                'type' => 'travel_request.approved',
                'message' => 'Seu pedido de viagem para Wano foi aprovado.',
                'data' => '{"travel_request_id":"' . DecisionMother::TRAVEL_REQUEST_ID . '","status":"approved"}',
                'read_at' => $readAt,
                'created_at' => '2026-11-10 09:30:00',
            ]);
    }

    /**
     * Ids that sort in creation order, as UUIDv7 do.
     */
    protected static function notificationId(int $sequence): string
    {
        return \sprintf('019a6c00-0000-7000-8000-%012d', $sequence);
    }
}
