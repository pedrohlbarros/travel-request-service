<?php

declare(strict_types=1);

namespace Application\Identity\UseCase;

use Application\Identity\Input\CreateUserInput;
use Application\Identity\Output\UserOutput;
use Application\Identity\Port\PasswordHasher;
use Application\Shared\Port\Clock;
use Application\Shared\Port\IdentifierGenerator;
use Domain\Identity\Entity\User;
use Domain\Identity\Exception\EmailAlreadyRegistered;
use Domain\Identity\Exception\InvalidEmail;
use Domain\Identity\Exception\InvalidPassword;
use Domain\Identity\Exception\InvalidUserName;
use Domain\Identity\Repository\UserRepository;
use Domain\Identity\ValueObject\Email;
use Domain\Identity\ValueObject\PlainPassword;
use Domain\Identity\ValueObject\UserId;
use Domain\Identity\ValueObject\UserName;

final readonly class CreateUserUseCase
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private IdentifierGenerator $identifiers,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidUserName
     * @throws InvalidEmail
     * @throws InvalidPassword
     * @throws EmailAlreadyRegistered
     */
    public function execute(CreateUserInput $input): UserOutput
    {
        $name = UserName::fromString(name: $input->name);
        $email = Email::fromString(address: $input->email);
        $password = PlainPassword::fromString(password: $input->password);

        if ($this->users->findByEmail(email: $email) !== null) {
            throw EmailAlreadyRegistered::address(email: $email);
        }

        $userId = UserId::fromString(id: $this->identifiers->uuid7());
        $user = User::register(
            id: $userId,
            name: $name,
            email: $email,
            passwordHash: $this->passwordHasher->hash(password: $password),
            role: $input->role,
            registeredAt: $this->clock->now(),
        );
        $this->users->add(user: $user);

        return UserOutput::fromEntity(user: $user);
    }
}
