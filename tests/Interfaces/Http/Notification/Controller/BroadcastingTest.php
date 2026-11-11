<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Notification\Controller;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LaravelTestCase;
use Tests\Support\TravelRequest\CallsTravelRequestApi;

final class BroadcastingTest extends LaravelTestCase
{
    use CallsTravelRequestApi;
    use RefreshDatabase;

    private const string CHANNEL_AUTH = '/broadcasting/auth';

    private const string SOCKET_ID = '1234.5678';

    private const string PLAYGROUND = '/docs/notifications';

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInEveryone();
    }

    public function test_AUserMayListenOnTheirOwnChannel(): void
    {
        $response = $this->postJson(
            self::CHANNEL_AUTH,
            ['socket_id' => self::SOCKET_ID, 'channel_name' => 'private-users.' . $this->requesterId],
            $this->bearer(accessToken: $this->requesterToken),
        );
        $signature = $response->json('auth');

        $response->assertOk();
        self::assertIsString($signature);
        self::assertStringStartsWith('test-key:', $signature);
    }

    public function test_NobodyMayListenOnSomeoneElsesChannel(): void
    {
        $response = $this->postJson(
            self::CHANNEL_AUTH,
            ['socket_id' => self::SOCKET_ID, 'channel_name' => 'private-users.' . $this->requesterId],
            $this->bearer(accessToken: $this->adminToken),
        );

        $response->assertForbidden();
    }

    public function test_ListeningRequiresAnAccessToken(): void
    {
        $response = $this->postJson(
            self::CHANNEL_AUTH,
            ['socket_id' => self::SOCKET_ID, 'channel_name' => 'private-users.' . $this->requesterId],
        );

        $response->assertUnauthorized();
    }

    public function test_ThePlaygroundOpensLocallyWithTheReverbSettings(): void
    {
        $this->app->make(Config::class)
            ->set('app.env', 'local');
        $this->app->detectEnvironment(static fn(): string => 'local');

        $response = $this->get(self::PLAYGROUND);

        $response->assertOk()
            ->assertSee('Notificações ao vivo')
            ->assertSee('"key":"test-key"', escape: false)
            ->assertSee('.travel_request.decided', escape: false);
    }

    public function test_ThePlaygroundIsHiddenOutsideLocal(): void
    {
        $response = $this->get(self::PLAYGROUND);

        $response->assertNotFound();
    }
}
