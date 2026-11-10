<?php

declare(strict_types=1);

namespace Application\Identity\UseCase;

use Application\Identity\Output\UserOutput;
use Domain\Identity\Exception\UserNotFound;
use Domain\Identity\Repository\UserRepository;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Exception\InvalidIdentifier;

final readonly class GetUserUseCase
{
    public function __construct(
        private UserRepository $users,
    ) {}

    /**
     * @throws InvalidIdentifier
     * @throws UserNotFound
     */
    public function execute(string $userId): UserOutput
    {
        $user = $this->users->get(id: UserId::fromString(id: $userId));

        return UserOutput::fromEntity(user: $user);
    }
}
