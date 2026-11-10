<?php

declare(strict_types=1);

namespace Tests\Support\Identity\Fixture;

use DateTimeImmutable;
use DateTimeZone;
use Domain\Identity\Entity\User;
use Domain\Identity\Enum\Role;
use Domain\Identity\ValueObject\Email;
use Domain\Identity\ValueObject\PasswordHash;
use Domain\Identity\ValueObject\UserId;
use Domain\Identity\ValueObject\UserName;
use Tests\Support\Identity\Port\FakePasswordHasher;

final class IdentityMother
{
    public const string USER_ID = '019a6a20-aaaa-7bbb-8ccc-0000000000a1';

    public const string ADMIN_ID = '019a6a20-aaaa-7bbb-8ccc-0000000000b1';

    public const string NAME = 'Monkey D. Luffy';

    public const string EMAIL = 'luffy@example.com';

    public const string ADMIN_EMAIL = 'admin@example.com';

    public const string PASSWORD = 'gomu-gomu-no';

    public const string NOW = '2026-11-09 10:00:00';

    public const int ACCESS_TOKEN_SECONDS = 900;

    public const int REFRESH_TOKEN_SECONDS = 604800;

    public static function moment(string $moment = self::NOW): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('UTC'));
    }

    public static function user(string $id = self::USER_ID, string $email = self::EMAIL, Role $role = Role::User): User
    {
        return User::register(
            id: UserId::fromString(id: $id),
            name: UserName::fromString(name: self::NAME),
            email: Email::fromString(address: $email),
            passwordHash: new PasswordHash(value: FakePasswordHasher::HASH_PREFIX . self::PASSWORD),
            role: $role,
            registeredAt: self::moment(),
        );
    }

    public static function admin(): User
    {
        return self::user(id: self::ADMIN_ID, email: self::ADMIN_EMAIL, role: Role::Admin);
    }
}
