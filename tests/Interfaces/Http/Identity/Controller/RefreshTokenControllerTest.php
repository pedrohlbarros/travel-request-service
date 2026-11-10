<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Identity\Controller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Identity\AuthenticatesUsers;
use Tests\Support\LaravelTestCase;

final class RefreshTokenControllerTest extends LaravelTestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    private const string REFRESH = '/api/v1/auth/refresh';

    private const string EMAIL = 'robin@example.com';

    private const int ATTEMPTS_PER_MINUTE = 5;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createUser(email: self::EMAIL);
    }

    public function test_ExchangesTheRefreshTokenForANewPair(): void
    {
        $login = $this->loginAs(email: self::EMAIL);

        $response = $this->postJson(self::REFRESH, ['refresh_token' => $login->refreshToken]);

        $newAccessToken = $response->json('access_token');
        \assert(\is_string($newAccessToken));
        $meWithNewToken = $this->getJson('/api/v1/auth/me', $this->bearer(accessToken: $newAccessToken));

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer');
        self::assertNotSame($login->refreshToken, $response->json('refresh_token'));
        $meWithNewToken->assertOk();
    }

    public function test_ReusingARefreshTokenRevokesTheWholeFamily(): void
    {
        $login = $this->loginAs(email: self::EMAIL);
        $rotated = $this->postJson(self::REFRESH, ['refresh_token' => $login->refreshToken])
            ->json('refresh_token');

        $reuse = $this->postJson(self::REFRESH, ['refresh_token' => $login->refreshToken]);
        $afterReuse = $this->postJson(self::REFRESH, ['refresh_token' => $rotated]);

        $reuse->assertUnauthorized()
            ->assertJsonPath('type', '/problems/invalid-refresh-token');
        $afterReuse->assertUnauthorized()
            ->assertJsonPath('type', '/problems/invalid-refresh-token');
    }

    public function test_UnknownRefreshTokenIsRejected(): void
    {
        $this->postJson(self::REFRESH, ['refresh_token' => 'never-issued'])
            ->assertUnauthorized()
            ->assertJsonPath('type', '/problems/invalid-refresh-token');
    }

    public function test_MissingRefreshTokenIsAValidationError(): void
    {
        $this->postJson(self::REFRESH, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['refresh_token']);
    }

    public function test_SixthAttemptInAMinuteIsRateLimited(): void
    {
        for ($i = 0; $i < self::ATTEMPTS_PER_MINUTE; $i++) {
            $this->postJson(self::REFRESH, ['refresh_token' => 'never-issued'])
                ->assertUnauthorized();
        }

        $this->postJson(self::REFRESH, ['refresh_token' => 'never-issued'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }
}
