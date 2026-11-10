<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Persistence;

use Domain\Identity\Entity\User;
use Domain\Identity\Enum\Role;
use Domain\Identity\Exception\EmailAlreadyRegistered;
use Domain\Identity\Exception\UserNotFound;
use Domain\Identity\Repository\UserRepository;
use Domain\Identity\ValueObject\Email;
use Domain\Identity\ValueObject\PasswordHash;
use Domain\Identity\ValueObject\UserId;
use Domain\Identity\ValueObject\UserName;
use Illuminate\Database\UniqueConstraintViolationException;
use Infrastructure\Shared\Persistence\BinaryUuid;

final readonly class EloquentUserRepository implements UserRepository
{
    public function get(UserId $id): User
    {
        $idBytes = BinaryUuid::toBytes(uuid: $id->toString());
        $userRow = UserModel::query()
            ->whereKey($idBytes)
            ->first();

        if ($userRow === null) {
            throw UserNotFound::withId(id: $id);
        }

        return $this->toEntity(userRow: $userRow);
    }

    public function findByEmail(Email $email): ?User
    {
        $userRow = UserModel::query()
            ->where('email', $email->address)
            ->first();

        return $userRow === null ? null : $this->toEntity(userRow: $userRow);
    }

    public function add(User $user): void
    {
        try {
            UserModel::query()
                ->create([
                    'id' => $user->id->toString(),
                    'name' => $user->name->value,
                    'email' => $user->email->address,
                    'password' => $user->passwordHash->value,
                    'role' => $user->role->value,
                    'created_at' => $user->createdAt,
                    'updated_at' => $user->createdAt,
                ]);
        } catch (UniqueConstraintViolationException) {
            throw EmailAlreadyRegistered::address(email: $user->email);
        }
    }

    private function toEntity(UserModel $userRow): User
    {
        return User::reconstitute(
            id: UserId::fromString(id: $userRow->id),
            name: UserName::fromString(name: $userRow->name),
            email: Email::fromString(address: $userRow->email),
            passwordHash: new PasswordHash(value: $userRow->password),
            role: Role::from(value: $userRow->role),
            createdAt: $userRow->created_at->toDateTimeImmutable(),
        );
    }
}
