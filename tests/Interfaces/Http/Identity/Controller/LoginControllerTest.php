<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Identity\Controller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Identity\AuthenticatesUsers;
use Tests\Support\Identity\JwtSegments;
use Tests\Support\LaravelTestCase;

final class LoginControllerTest extends LaravelTestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    private const string LOGIN = '/api/v1/auth/login';

    private const string EMAIL = 'robin@example.com';

    private const int ATTEMPTS_PER_MINUTE = 5;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createUser(email: self::EMAIL);
    }

    public function test_ReturnsAnOAuthTokenResponse(): void
    {
        $response = $this->postJson(self::LOGIN, ['email' => self::EMAIL, 'password' => self::TEST_PASSWORD]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 900)
            ->assertJsonPath('refresh_expires_in', 604800)
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'refresh_token', 'refresh_expires_in']);
    }

    public function test_TokenCarriesNoRoleNorProfileData(): void
    {
        $response = $this->postJson(self::LOGIN, ['email' => self::EMAIL, 'password' => self::TEST_PASSWORD]);

        $accessToken = $response->json('access_token');
        \assert(\is_string($accessToken));
        $claims = JwtSegments::claims(token: $accessToken);

        self::assertSame(['iss', 'aud', 'sub', 'jti', 'iat', 'nbf', 'exp'], array_keys($claims));
    }

    public function test_WrongPasswordAndUnknownEmailLookTheSame(): void
    {
        $wrongPassword = $this->postJson(self::LOGIN, ['email' => self::EMAIL, 'password' => 'wrong-password']);
        $unknownEmail = $this->postJson(
            self::LOGIN,
            ['email' => 'nobody@example.com', 'password' => self::TEST_PASSWORD],
        );

        $wrongPassword->assertUnauthorized()
            ->assertJsonPath('type', '/problems/invalid-credentials');
        $unknownEmail->assertUnauthorized()
            ->assertJsonPath('type', '/problems/invalid-credentials');
        self::assertSame($wrongPassword->json('detail'), $unknownEmail->json('detail'));
    }

    public function test_MissingFieldsAreAValidationError(): void
    {
        $this->postJson(self::LOGIN, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_SixthAttemptInAMinuteIsRateLimited(): void
    {
        for ($i = 0; $i < self::ATTEMPTS_PER_MINUTE; $i++) {
            $this->postJson(self::LOGIN, ['email' => self::EMAIL, 'password' => 'wrong-password'])
                ->assertUnauthorized();
        }

        $response = $this->postJson(self::LOGIN, ['email' => self::EMAIL, 'password' => self::TEST_PASSWORD]);

        $response->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', '/problems/too-many-requests');
    }

    public function test_RateLimitIsPerEmail(): void
    {
        for ($i = 0; $i < self::ATTEMPTS_PER_MINUTE; $i++) {
            $this->postJson(self::LOGIN, ['email' => self::EMAIL, 'password' => 'wrong-password']);
        }
        $this->createUser(email: 'franky@example.com');

        $this->postJson(self::LOGIN, ['email' => 'franky@example.com', 'password' => self::TEST_PASSWORD])
            ->assertOk();
    }
}
