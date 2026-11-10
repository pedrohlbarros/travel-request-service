<?php

declare(strict_types=1);

namespace Tests\Support\Identity\Repository;

use Domain\Identity\Entity\User;
use Domain\Identity\Exception\EmailAlreadyRegistered;
use Domain\Identity\Exception\UserNotFound;
use Domain\Identity\Repository\UserRepository;
use Domain\Identity\ValueObject\Email;
use Domain\Identity\ValueObject\UserId;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $userById = [];

    public function get(UserId $id): User
    {
        $user = $this->userById[$id->toString()] ?? null;

        if ($user === null) {
            throw UserNotFound::withId(id: $id);
        }

        return $user;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->userById as $user) {
            if ($user->email->equals(other: $email)) {
                return $user;
            }
        }

        return null;
    }

    public function add(User $user): void
    {
        if ($this->findByEmail(email: $user->email) !== null) {
            throw EmailAlreadyRegistered::address(email: $user->email);
        }

        $this->userById[$user->id->toString()] = $user;
    }

    /**
     * @return list<User>
     */
    public function all(): array
    {
        return array_values($this->userById);
    }
}
