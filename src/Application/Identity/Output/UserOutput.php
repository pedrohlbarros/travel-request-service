<?php

declare(strict_types=1);

namespace Application\Identity\Output;

use DateTimeImmutable;
use Domain\Identity\Entity\User;
use Domain\Identity\Enum\Role;

final readonly class UserOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public Role $role,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->id->toString(),
            name: $user->name->value,
            email: $user->email->address,
            role: $user->role,
            createdAt: $user->createdAt,
        );
    }
}
