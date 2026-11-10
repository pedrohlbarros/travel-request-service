<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Identity\Token;

use Application\Identity\Exception\InvalidAccessToken;
use Application\Identity\Port\IssuedAccessToken;
use DateTimeImmutable;
use Domain\Identity\ValueObject\UserId;
use Infrastructure\Identity\Token\JwtKeyPair;
use Infrastructure\Identity\Token\JwtSettings;
use Infrastructure\Identity\Token\LcobucciAccessTokens;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Identity\Fixture\IdentityMother as Mother;
use Tests\Support\Identity\JwtSegments;
use Tests\Support\Identity\TestJwtKeys;
use Tests\Support\LaravelTestCase;
use Tests\Support\Shared\Port\SequentialIdentifierGenerator;

final class LcobucciAccessTokensTest extends LaravelTestCase
{
    private const string ISSUER = 'http://localhost:8000';

    private const string AUDIENCE = 'travel-request-service';

    private const string EXPIRES_AT = '2026-11-09 10:15:00';

    private const string UNUSED_PATH = 'unused';

    public function test_VerifiesWhatItIssued(): void
    {
        $tokens = $this->tokens();
        $issued = $this->issueWith(tokens: $tokens);

        $verified = $tokens->verify(token: $issued->token, now: Mother::moment(moment: '2026-11-09 10:14:59'));

        self::assertSame(Mother::USER_ID, $verified->userId);
        self::assertSame($issued->tokenId, $verified->tokenId);
        self::assertSame(SequentialIdentifierGenerator::nth(sequence: 1), $verified->tokenId);
        self::assertEquals(Mother::moment(moment: self::EXPIRES_AT), $verified->expiresAt);
    }

    public function test_HeaderNamesTheKeyAndClaimsCarryNoProfileData(): void
    {
        $keys = $this->signingKeys();

        $issued = $this->issueWith(tokens: $this->tokens());

        $header = JwtSegments::header(token: $issued->token);
        $claims = JwtSegments::claims(token: $issued->token);
        $issuedAt = Mother::moment()
            ->getTimestamp();

        self::assertSame(['typ' => 'JWT', 'alg' => 'RS256', 'kid' => $keys->keyId], $header);
        self::assertSame(
            [
                'iss' => self::ISSUER,
                'aud' => self::AUDIENCE,
                'sub' => Mother::USER_ID,
                'jti' => $issued->tokenId,
                'iat' => $issuedAt,
                'nbf' => $issuedAt,
                'exp' => Mother::moment(moment: self::EXPIRES_AT)
                    ->getTimestamp(),
            ],
            $claims,
        );
    }

    public function test_TimestampsAreWholeSeconds(): void
    {
        $issuedAtWithMicroseconds = new DateTimeImmutable('2026-11-09 10:00:00.654321');

        $issued = $this->issueWith(tokens: $this->tokens(), issuedAt: $issuedAtWithMicroseconds);

        $claims = JwtSegments::claims(token: $issued->token);

        self::assertIsInt($claims['iat']);
        self::assertIsInt($claims['nbf']);
        self::assertIsInt($claims['exp']);
    }

    public function test_RejectsAnExpiredToken(): void
    {
        $tokens = $this->tokens();
        $issued = $this->issueWith(tokens: $tokens);

        $this->expectException(InvalidAccessToken::class);

        $tokens->verify(token: $issued->token, now: Mother::moment(moment: self::EXPIRES_AT));
    }

    public function test_RejectsATokenUsedBeforeItsNotBefore(): void
    {
        $tokens = $this->tokens();
        $issued = $this->issueWith(tokens: $tokens);

        $this->expectException(InvalidAccessToken::class);

        $tokens->verify(token: $issued->token, now: Mother::moment(moment: '2026-11-09 09:59:59'));
    }

    /**
     * @return array<string, array{non-empty-string, non-empty-string}>
     */
    public static function foreignIssuersAndAudiences(): array
    {
        return [
            'otherIssuer' => ['https://evil.example.com', self::AUDIENCE],
            'otherAudience' => [self::ISSUER, 'another-service'],
        ];
    }

    /**
     * @param non-empty-string $issuer
     * @param non-empty-string $audience
     */
    #[DataProvider('foreignIssuersAndAudiences')]
    public function test_RejectsATokenForAnotherIssuerOrAudience(string $issuer, string $audience): void
    {
        $foreignSettings = new JwtSettings(
            issuer: $issuer,
            audience: $audience,
            privateKeyPath: self::UNUSED_PATH,
            publicKeyPath: self::UNUSED_PATH,
        );
        $foreignIssued = $this->issueWith(tokens: $this->tokens(settings: $foreignSettings));

        $this->expectException(InvalidAccessToken::class);

        $this->tokens()
            ->verify(token: $foreignIssued->token, now: Mother::moment());
    }

    public function test_RejectsATokenWithATamperedPayload(): void
    {
        $issued = $this->issueWith(tokens: $this->tokens());
        $forgedToken = JwtSegments::withClaims(token: $issued->token, forgedClaims: ['sub' => Mother::ADMIN_ID]);

        $this->expectException(InvalidAccessToken::class);

        $this->tokens()
            ->verify(token: $forgedToken, now: Mother::moment());
    }

    public function test_RejectsATokenSignedWithAnotherKey(): void
    {
        $foreignPaths = TestJwtKeys::paths(pairName: TestJwtKeys::FOREIGN_PAIR);
        $foreignKeys = JwtKeyPair::fromFiles(
            privateKeyPath: $foreignPaths['private'],
            publicKeyPath: $foreignPaths['public'],
        );
        $foreignTokens = new LcobucciAccessTokens(
            keys: $foreignKeys,
            settings: $this->settings(),
            identifiers: new SequentialIdentifierGenerator(),
        );
        $foreignIssued = $this->issueWith(tokens: $foreignTokens);

        $this->expectException(InvalidAccessToken::class);
        $this->expectExceptionMessageIs('The access token is not valid: it was signed with an unknown key.');

        $this->tokens()
            ->verify(token: $foreignIssued->token, now: Mother::moment());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedTokens(): array
    {
        return [
            'empty' => [''],
            'notAJwt' => ['not-a-jwt'],
            'garbageSegments' => ['aaa.bbb.ccc'],
        ];
    }

    #[DataProvider('malformedTokens')]
    public function test_RejectsMalformedTokens(string $malformedToken): void
    {
        $tokens = $this->tokens();

        $this->expectException(InvalidAccessToken::class);

        $tokens->verify(token: $malformedToken, now: Mother::moment());
    }

    private function issueWith(LcobucciAccessTokens $tokens, ?DateTimeImmutable $issuedAt = null): IssuedAccessToken
    {
        return $tokens->issue(
            userId: UserId::fromString(id: Mother::USER_ID),
            issuedAt: $issuedAt ?? Mother::moment(),
            expiresAt: Mother::moment(moment: self::EXPIRES_AT),
        );
    }

    private function tokens(?JwtSettings $settings = null): LcobucciAccessTokens
    {
        return new LcobucciAccessTokens(
            keys: $this->signingKeys(),
            settings: $settings ?? $this->settings(),
            identifiers: new SequentialIdentifierGenerator(),
        );
    }

    private function settings(): JwtSettings
    {
        return new JwtSettings(
            issuer: self::ISSUER,
            audience: self::AUDIENCE,
            privateKeyPath: self::UNUSED_PATH,
            publicKeyPath: self::UNUSED_PATH,
        );
    }

    private function signingKeys(): JwtKeyPair
    {
        $paths = TestJwtKeys::paths(pairName: TestJwtKeys::SIGNING_PAIR);

        return JwtKeyPair::fromFiles(privateKeyPath: $paths['private'], publicKeyPath: $paths['public']);
    }
}
