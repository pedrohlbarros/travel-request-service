<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Identity\Controller;

use Application\Shared\Port\Clock;
use Application\Shared\Port\IdentifierGenerator;
use DateTimeImmutable;
use DateTimeZone;
use Domain\Identity\Enum\Role;
use Domain\Identity\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Identity\Token\JwtKeyPair;
use Infrastructure\Identity\Token\JwtSettings;
use Infrastructure\Identity\Token\LcobucciAccessTokens;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Identity\AuthenticatesUsers;
use Tests\Support\LaravelTestCase;
use Tests\Support\Shared\Port\FrozenClock;

final class MeControllerTest extends LaravelTestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    private const string ME = '/api/v1/auth/me';

    private const string EMAIL = 'robin@example.com';

    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->clock = new FrozenClock(frozenAt: $now);
        $this->app->instance(Clock::class, $this->clock);
    }

    public function test_ReturnsTheCallerAndTheirRole(): void
    {
        $admin = $this->createUser(email: self::EMAIL, role: Role::Admin);
        $login = $this->loginAs(email: self::EMAIL);

        $response = $this->getJson(self::ME, $this->bearer(accessToken: $login->accessToken));

        $response->assertOk()
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.email', self::EMAIL)
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_WithoutTokenAsksForBearerAuthentication(): void
    {
        $this->getJson(self::ME)
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer')
            ->assertJsonPath('type', '/problems/unauthenticated');
    }

    public function test_WithoutTokenOrJsonAcceptHeaderIsStillAProblemNotARedirect(): void
    {
        $response = $this->get(self::ME);

        $response->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', '/problems/unauthenticated');
    }

    public function test_ExpiredTokenIsRejected(): void
    {
        $this->createUser(email: self::EMAIL);
        $login = $this->loginAs(email: self::EMAIL);

        $this->clock->travelTo(moment: $this->clock->now()
            ->modify('+15 minutes'));

        $this->getJson(self::ME, $this->bearer(accessToken: $login->accessToken))
            ->assertUnauthorized();
    }

    /**
     * @return array<string, array{non-empty-string, non-empty-string}>
     */
    public static function foreignIssuersAndAudiences(): array
    {
        return [
            'wrongIssuer' => ['https://evil.example.com', 'travel-request-service'],
            'wrongAudience' => ['http://localhost:8000', 'another-service'],
        ];
    }

    /**
     * @param non-empty-string $issuer
     * @param non-empty-string $audience
     */
    #[DataProvider('foreignIssuersAndAudiences')]
    public function test_TokenForAnotherIssuerOrAudienceIsRejected(string $issuer, string $audience): void
    {
        $user = $this->createUser(email: self::EMAIL);
        $foreignTokens = new LcobucciAccessTokens(
            keys: $this->app->make(JwtKeyPair::class),
            settings: new JwtSettings(
                issuer: $issuer,
                audience: $audience,
                privateKeyPath: 'unused',
                publicKeyPath: 'unused',
            ),
            identifiers: $this->app->make(IdentifierGenerator::class),
        );
        $now = $this->clock->now();
        $foreignToken = $foreignTokens->issue(
            userId: UserId::fromString(id: $user->id),
            issuedAt: $now,
            expiresAt: $now->modify('+15 minutes'),
        )->token;

        $this->getJson(self::ME, $this->bearer(accessToken: $foreignToken))
            ->assertUnauthorized();
    }

    public function test_TokenWithATamperedSignatureIsRejected(): void
    {
        $this->createUser(email: self::EMAIL);
        $accessToken = $this->loginAs(email: self::EMAIL)->accessToken;
        $tamperedToken = substr($accessToken, 0, -4) . (str_ends_with($accessToken, 'AAAA') ? 'BBBB' : 'AAAA');

        $this->getJson(self::ME, $this->bearer(accessToken: $tamperedToken))
            ->assertUnauthorized();
    }
}
