<?php

declare(strict_types=1);

namespace Application\Identity\UseCase;

use Application\Identity\Input\LoginInput;
use Application\Identity\Output\TokenPairOutput;
use Application\Identity\Port\PasswordHasher;
use Application\Identity\Service\TokenPairIssuer;
use Application\Shared\Port\Clock;
use Application\Shared\Port\IdentifierGenerator;
use Domain\Identity\Exception\InvalidCredentials;
use Domain\Identity\Exception\InvalidEmail;
use Domain\Identity\Repository\UserRepository;
use Domain\Identity\ValueObject\Email;
use Domain\Identity\ValueObject\RefreshTokenFamilyId;

final readonly class LoginUseCase
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private TokenPairIssuer $tokenPairs,
        private IdentifierGenerator $identifiers,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidEmail
     * @throws InvalidCredentials
     */
    public function execute(LoginInput $input): TokenPairOutput
    {
        $user = $this->users->findByEmail(email: Email::fromString(address: $input->email));
        $isPasswordCorrect = $this->passwordHasher->verify(password: $input->password, hash: $user?->passwordHash);

        if ($user === null || ! $isPasswordCorrect) {
            throw InvalidCredentials::create();
        }

        $familyId = RefreshTokenFamilyId::fromString(id: $this->identifiers->uuid7());

        return $this->tokenPairs->issue(userId: $user->id, familyId: $familyId, issuedAt: $this->clock->now());
    }
}
