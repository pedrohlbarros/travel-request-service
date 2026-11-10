<?php

declare(strict_types=1);

namespace Domain\Identity\Entity;

use DateTimeImmutable;
use Domain\Identity\Enum\Role;
use Domain\Identity\ValueObject\Email;
use Domain\Identity\ValueObject\PasswordHash;
use Domain\Identity\ValueObject\UserId;
use Domain\Identity\ValueObject\UserName;

final readonly class User
{
    private function __construct(
        public UserId $id,
        public UserName $name,
        public Email $email,
        public PasswordHash $passwordHash,
        public Role $role,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function register(
        UserId $id,
        UserName $name,
        Email $email,
        PasswordHash $passwordHash,
        Role $role,
        DateTimeImmutable $registeredAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            email: $email,
            passwordHash: $passwordHash,
            role: $role,
            createdAt: $registeredAt,
        );
    }

    public static function reconstitute(
        UserId $id,
        UserName $name,
        Email $email,
        PasswordHash $passwordHash,
        Role $role,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            email: $email,
            passwordHash: $passwordHash,
            role: $role,
            createdAt: $createdAt,
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }
}
