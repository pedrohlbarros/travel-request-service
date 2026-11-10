<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Identity\Controller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Identity\AuthenticatesUsers;
use Tests\Support\LaravelTestCase;

final class LogoutControllerTest extends LaravelTestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    private const string LOGOUT = '/api/v1/auth/logout';

    private const string EMAIL = 'robin@example.com';

    public function test_LogoutEndsBothTheAccessAndTheRefreshToken(): void
    {
        $this->createUser(email: self::EMAIL);
        $login = $this->loginAs(email: self::EMAIL);

        $response = $this->postJson(
            self::LOGOUT,
            ['refresh_token' => $login->refreshToken],
            $this->bearer(accessToken: $login->accessToken),
        );

        $this->forgetGuards();
        $meAfterLogout = $this->getJson('/api/v1/auth/me', $this->bearer(accessToken: $login->accessToken));
        $refreshAfterLogout = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $login->refreshToken]);

        $response->assertNoContent();
        $meAfterLogout->assertUnauthorized();
        $refreshAfterLogout->assertUnauthorized();
    }

    public function test_RequiresAnAccessToken(): void
    {
        $this->postJson(self::LOGOUT, ['refresh_token' => 'anything'])
            ->assertUnauthorized()
            ->assertJsonPath('type', '/problems/unauthenticated');
    }

    private function forgetGuards(): void
    {
        $this->app->make('auth')
            ->forgetGuards();
    }
}
