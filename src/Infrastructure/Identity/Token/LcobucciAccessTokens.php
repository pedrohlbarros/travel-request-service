<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Token;

use Application\Identity\Exception\InvalidAccessToken;
use Application\Identity\Port\AccessTokenIssuer;
use Application\Identity\Port\AccessTokenVerifier;
use Application\Identity\Port\IssuedAccessToken;
use Application\Identity\Port\VerifiedAccessToken;
use Application\Shared\Port\IdentifierGenerator;
use DateTimeImmutable;
use Domain\Identity\ValueObject\UserId;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Validator;
use Throwable;

final readonly class LcobucciAccessTokens implements AccessTokenIssuer, AccessTokenVerifier
{
    private const string KEY_ID_HEADER = 'kid';

    public function __construct(
        private JwtKeyPair $keys,
        private JwtSettings $settings,
        private IdentifierGenerator $identifiers,
    ) {}

    public function issue(UserId $userId, DateTimeImmutable $issuedAt, DateTimeImmutable $expiresAt): IssuedAccessToken
    {
        $tokenId = $this->identifiers->uuid7();
        $subject = $userId->toString();
        \assert($subject !== '');

        $token = Builder::new(new JoseEncoder(), ChainedFormatter::withUnixTimestampDates())
            ->withHeader(self::KEY_ID_HEADER, $this->keys->keyId)
            ->issuedBy($this->settings->issuer)
            ->permittedFor($this->settings->audience)
            ->relatedTo($subject)
            ->identifiedBy($tokenId)
            ->issuedAt($issuedAt)
            ->canOnlyBeUsedAfter($issuedAt)
            ->expiresAt($expiresAt)
            ->getToken(new Sha256(), InMemory::plainText($this->keys->privateKeyPem));

        return new IssuedAccessToken(token: $token->toString(), tokenId: $tokenId);
    }

    public function verify(string $token, DateTimeImmutable $now): VerifiedAccessToken
    {
        $parsedToken = $this->parse(token: $token);

        $keyId = $parsedToken->headers()
            ->get(self::KEY_ID_HEADER);

        if ($keyId !== $this->keys->keyId) {
            throw InvalidAccessToken::because(reason: 'it was signed with an unknown key');
        }

        $signedWithOurKey = new SignedWith(new Sha256(), InMemory::plainText($this->keys->publicKeyPem));

        $validAtNow = new StrictValidAt(new FixedPsrClock(now: $now));

        try {
            new Validator()->assert(
                $parsedToken,
                $signedWithOurKey,
                new IssuedBy($this->settings->issuer),
                new PermittedFor($this->settings->audience),
                $validAtNow,
            );
        } catch (RequiredConstraintsViolated $violation) {
            throw InvalidAccessToken::because(reason: $violation->getMessage());
        }

        return $this->verifiedClaimsOf(token: $parsedToken);
    }

    private function parse(string $token): Plain
    {
        if ($token === '') {
            throw InvalidAccessToken::because(reason: 'it is empty');
        }

        try {
            $parsedToken = new Parser(new JoseEncoder())->parse($token);
        } catch (Throwable) {
            throw InvalidAccessToken::because(reason: 'it is malformed');
        }

        \assert($parsedToken instanceof Plain);

        return $parsedToken;
    }

    private function verifiedClaimsOf(Plain $token): VerifiedAccessToken
    {
        $claims = $token->claims();
        $subject = $claims->get(RegisteredClaims::SUBJECT);
        $tokenId = $claims->get(RegisteredClaims::ID);
        $expiresAt = $claims->get(RegisteredClaims::EXPIRATION_TIME);

        if (! \is_string($subject) || ! \is_string($tokenId) || ! $expiresAt instanceof DateTimeImmutable) {
            throw InvalidAccessToken::because(reason: 'it lacks sub, jti or exp');
        }

        return new VerifiedAccessToken(userId: $subject, tokenId: $tokenId, expiresAt: $expiresAt);
    }
}
