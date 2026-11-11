<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Persistence;

use Application\Identity\Port\UserRoles;
use Domain\Identity\Enum\Role;
use Domain\Identity\ValueObject\UserId;
use Infrastructure\Shared\Persistence\BinaryUuid;

/**
 * Reads only the role column, from the primary: a token can arrive right after its user registered.
 */
final readonly class EloquentUserRoles implements UserRoles
{
    public function roleOf(UserId $id): ?Role
    {
        $idBytes = BinaryUuid::toBytes(uuid: $id->toString());
        $role = UserModel::onPrimary()
            ->whereKey($idBytes)
            ->value('role');

        return \is_string($role) ? Role::from(value: $role) : null;
    }
}
