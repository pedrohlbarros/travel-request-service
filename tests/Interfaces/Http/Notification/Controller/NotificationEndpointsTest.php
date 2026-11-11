<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Notification\Controller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LaravelTestCase;
use Tests\Support\Notification\Fixture\StoresNotifications;
use Tests\Support\TravelRequest\CallsTravelRequestApi;

final class NotificationEndpointsTest extends LaravelTestCase
{
    use CallsTravelRequestApi;
    use RefreshDatabase;
    use StoresNotifications;

    private const string NOTIFICATIONS = '/api/v1/notifications';

    private const string FIRST_READ_AT = '2026-11-10 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInEveryone();
    }

    public function test_ListsTheCallersOwnNotificationsNewestFirst(): void
    {
        $this->storeNotification(id: self::notificationId(sequence: 1), recipientId: $this->requesterId);
        $this->storeNotification(id: self::notificationId(sequence: 2), recipientId: $this->requesterId);
        $this->storeNotification(id: self::notificationId(sequence: 3), recipientId: $this->adminId);

        $response = $this->getJson(self::NOTIFICATIONS, $this->bearer(accessToken: $this->requesterToken));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', self::notificationId(sequence: 2))
            ->assertJsonPath('data.0.type', 'travel_request.approved')
            ->assertJsonPath('data.0.message', 'Seu pedido de viagem para Wano foi aprovado.')
            ->assertJsonPath('data.0.data.status', 'approved')
            ->assertJsonPath('data.0.read_at', null)
            ->assertJsonPath('data.0.created_at', '2026-11-10T09:30:00+00:00')
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('links.next', null);
    }

    public function test_AnAdminSeesOnlyTheirOwnToo(): void
    {
        $this->storeNotification(id: self::notificationId(sequence: 1), recipientId: $this->requesterId);

        $response = $this->getJson(self::NOTIFICATIONS, $this->bearer(accessToken: $this->adminToken));

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_CanListOnlyTheUnreadAndPageThroughThem(): void
    {
        $this->storeNotification(
            id: self::notificationId(sequence: 1),
            recipientId: $this->requesterId,
            readAt: self::FIRST_READ_AT,
        );
        $this->storeNotification(id: self::notificationId(sequence: 2), recipientId: $this->requesterId);
        $this->storeNotification(id: self::notificationId(sequence: 3), recipientId: $this->requesterId);

        $firstPage = $this->getJson(
            self::NOTIFICATIONS . '?unread=true&per_page=1',
            $this->bearer(accessToken: $this->requesterToken),
        );
        $nextCursor = $firstPage->json('meta.next_cursor');
        \assert(\is_string($nextCursor));
        $secondPage = $this->getJson(
            self::NOTIFICATIONS . '?unread=true&per_page=1&cursor=' . $nextCursor,
            $this->bearer(accessToken: $this->requesterToken),
        );

        $firstPage->assertOk()
            ->assertJsonPath('data.0.id', self::notificationId(sequence: 3));
        $secondPage->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', self::notificationId(sequence: 2))
            ->assertJsonPath('meta.next_cursor', null);
    }

    public function test_RejectsAnInvalidListQuery(): void
    {
        $response = $this->getJson(
            self::NOTIFICATIONS . '?unread=maybe&per_page=500',
            $this->bearer(accessToken: $this->requesterToken),
        );

        $response->assertUnprocessable()
            ->assertJsonPath('type', '/problems/validation-failed')
            ->assertJsonValidationErrors(['unread', 'per_page']);
    }

    public function test_RejectsACursorTheListDidNotIssue(): void
    {
        $response = $this->getJson(
            self::NOTIFICATIONS . '?cursor=nonsense',
            $this->bearer(accessToken: $this->requesterToken),
        );

        $response->assertUnprocessable()
            ->assertJsonPath('type', '/problems/validation-failed');
    }

    public function test_MarkingReadReturnsTheNotificationAndKeepsTheFirstReadTime(): void
    {
        $this->storeNotification(id: self::notificationId(sequence: 1), recipientId: $this->requesterId);
        $path = self::NOTIFICATIONS . '/' . self::notificationId(sequence: 1) . '/read';

        $firstRead = $this->postJson($path, [], $this->bearer(accessToken: $this->requesterToken));
        $readAt = $firstRead->json('data.read_at');
        $secondRead = $this->postJson($path, [], $this->bearer(accessToken: $this->requesterToken));

        $firstRead->assertOk()
            ->assertJsonPath('data.id', self::notificationId(sequence: 1));
        self::assertIsString($readAt);
        $secondRead->assertOk()
            ->assertJsonPath('data.read_at', $readAt);
    }

    public function test_SomeoneElsesNotificationIsNotFound(): void
    {
        $this->storeNotification(id: self::notificationId(sequence: 1), recipientId: $this->adminId);
        $path = self::NOTIFICATIONS . '/' . self::notificationId(sequence: 1) . '/read';

        $response = $this->postJson($path, [], $this->bearer(accessToken: $this->requesterToken));

        $response->assertNotFound()
            ->assertJsonPath('type', '/problems/not-found');
    }

    public function test_AnIdThatIsNotAUuidIsNotFound(): void
    {
        $response = $this->postJson(
            self::NOTIFICATIONS . '/42/read',
            [],
            $this->bearer(accessToken: $this->requesterToken),
        );

        $response->assertNotFound();
    }

    public function test_RequiresAnAccessToken(): void
    {
        $response = $this->getJson(self::NOTIFICATIONS);

        $response->assertUnauthorized()
            ->assertJsonPath('type', '/problems/unauthenticated');
    }
}
