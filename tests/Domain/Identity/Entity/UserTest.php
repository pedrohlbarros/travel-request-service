<?php

declare(strict_types=1);

namespace Tests\Domain\Identity\Entity;

use Domain\Identity\Entity\User;
use Domain\Identity\Enum\Role;
use Domain\Identity\ValueObject\Email;
use Domain\Identity\ValueObject\PasswordHash;
use Domain\Identity\ValueObject\UserId;
use Domain\Identity\ValueObject\UserName;
use PHPUnit\Framework\TestCase;
use Tests\Support\Identity\Fixture\IdentityMother as Mother;

final class UserTest extends TestCase
{
    private const string PASSWORD_HASH = 'stored-hash';

    public function test_RegisteredUserKeepsEveryField(): void
    {
        $registeredAt = Mother::moment();

        $user = User::register(
            id: UserId::fromString(id: Mother::USER_ID),
            name: UserName::fromString(name: Mother::NAME),
            email: Email::fromString(address: Mother::EMAIL),
            passwordHash: new PasswordHash(value: self::PASSWORD_HASH),
            role: Role::User,
            registeredAt: $registeredAt,
        );

        self::assertSame(Mother::USER_ID, $user->id->toString());
        self::assertSame(Mother::NAME, $user->name->value);
        self::assertSame(Mother::EMAIL, $user->email->address);
        self::assertSame(self::PASSWORD_HASH, $user->passwordHash->value);
        self::assertSame(Role::User, $user->role);
        self::assertEquals($registeredAt, $user->createdAt);
    }

    public function test_ReconstituteRestoresEveryField(): void
    {
        $createdAt = Mother::moment();

        $user = User::reconstitute(
            id: UserId::fromString(id: Mother::ADMIN_ID),
            name: UserName::fromString(name: Mother::NAME),
            email: Email::fromString(address: Mother::ADMIN_EMAIL),
            passwordHash: new PasswordHash(value: self::PASSWORD_HASH),
            role: Role::Admin,
            createdAt: $createdAt,
        );

        self::assertSame(Mother::ADMIN_ID, $user->id->toString());
        self::assertSame(Mother::ADMIN_EMAIL, $user->email->address);
        self::assertSame(self::PASSWORD_HASH, $user->passwordHash->value);
        self::assertSame(Role::Admin, $user->role);
        self::assertEquals($createdAt, $user->createdAt);
    }

    public function test_OnlyAdminRoleIsAdmin(): void
    {
        self::assertTrue(Mother::admin()
            ->isAdmin());
        self::assertFalse(Mother::user()
            ->isAdmin());
    }
}
