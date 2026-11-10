<?php

declare(strict_types=1);

namespace Tests\Support\Identity\Port;

use Application\Identity\Exception\InvalidAccessToken;
use Application\Identity\Port\AccessTokenIssuer;
use Application\Identity\Port\AccessTokenVerifier;
use Application\Identity\Port\IssuedAccessToken;
use Application\Identity\Port\VerifiedAccessToken;
use DateTimeImmutable;
use Domain\Identity\ValueObject\UserId;

/**
 * Readable tokens ("access|<user>|<token id>|<expiry>") that only this fake accepts back.
 */
final class FakeAccessTokens implements AccessTokenIssuer, AccessTokenVerifier
{
    private const string SEPARATOR = '|';

    private const string MARKER = 'access';

    private int $lastTokenSequence = 0;

    /** @var list<array{userId: string, issuedAt: DateTimeImmutable, expiresAt: DateTimeImmutable}> */
    public private(set) array $issuedTokens = [];

    public static function tokenFor(string $userId, string $tokenId, DateTimeImmutable $expiresAt): string
    {
        return implode(self::SEPARATOR, [self::MARKER, $userId, $tokenId, $expiresAt->format(DATE_ATOM)]);
    }

    public function issue(UserId $userId, DateTimeImmutable $issuedAt, DateTimeImmutable $expiresAt): IssuedAccessToken
    {
        $this->lastTokenSequence++;
        $tokenId = 'token-' . $this->lastTokenSequence;
        $this->issuedTokens[] = ['userId' => $userId->toString(), 'issuedAt' => $issuedAt, 'expiresAt' => $expiresAt];

        $token = self::tokenFor(userId: $userId->toString(), tokenId: $tokenId, expiresAt: $expiresAt);

        return new IssuedAccessToken(token: $token, tokenId: $tokenId);
    }

    public function verify(string $token, DateTimeImmutable $now): VerifiedAccessToken
    {
        $tokenParts = explode(self::SEPARATOR, $token);

        if (\count($tokenParts) !== 4 || $tokenParts[0] !== self::MARKER) {
            throw InvalidAccessToken::because(reason: 'it is malformed');
        }

        [, $userId, $tokenId, $expiry] = $tokenParts;
        $expiresAt = new DateTimeImmutable($expiry);

        if ($now >= $expiresAt) {
            throw InvalidAccessToken::because(reason: 'it has expired');
        }

        return new VerifiedAccessToken(userId: $userId, tokenId: $tokenId, expiresAt: $expiresAt);
    }
}
