<?php

declare(strict_types=1);

namespace Tests\Application\Identity\UseCase;

use Application\Identity\Config\TokenLifetimes;
use Application\Identity\Service\TokenPairIssuer;
use PHPUnit\Framework\TestCase;
use Tests\Support\Identity\Fixture\IdentityMother as Mother;
use Tests\Support\Identity\Port\FakeAccessTokens;
use Tests\Support\Identity\Port\FakePasswordHasher;
use Tests\Support\Identity\Port\InMemoryAccessTokenRevocationList;
use Tests\Support\Identity\Port\SequentialOpaqueTokenGenerator;
use Tests\Support\Identity\Repository\InMemoryRefreshTokenRepository;
use Tests\Support\Identity\Repository\InMemoryUserRepository;
use Tests\Support\Shared\Port\FrozenClock;
use Tests\Support\Shared\Port\SequentialIdentifierGenerator;

/**
 * Wires every Identity port to its in-memory fake; abstract so each use case test only adds its subject.
 */
abstract class IdentityUseCaseTestCase extends TestCase
{
    protected InMemoryUserRepository $users;

    protected InMemoryRefreshTokenRepository $refreshTokens;

    protected InMemoryAccessTokenRevocationList $revokedAccessTokens;

    protected FakePasswordHasher $passwordHasher;

    protected FakeAccessTokens $accessTokens;

    protected SequentialIdentifierGenerator $identifiers;

    protected FrozenClock $clock;

    protected TokenPairIssuer $tokenPairs;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->refreshTokens = new InMemoryRefreshTokenRepository();
        $this->revokedAccessTokens = new InMemoryAccessTokenRevocationList();
        $this->passwordHasher = new FakePasswordHasher();
        $this->accessTokens = new FakeAccessTokens();
        $this->identifiers = new SequentialIdentifierGenerator();
        $this->clock = new FrozenClock(frozenAt: Mother::moment());
        $this->tokenPairs = new TokenPairIssuer(
            accessTokens: $this->accessTokens,
            opaqueTokens: new SequentialOpaqueTokenGenerator(),
            refreshTokens: $this->refreshTokens,
            identifiers: $this->identifiers,
            lifetimes: new TokenLifetimes(
                accessTokenSeconds: Mother::ACCESS_TOKEN_SECONDS,
                refreshTokenSeconds: Mother::REFRESH_TOKEN_SECONDS,
            ),
        );
    }
}
