<?php

declare(strict_types=1);

namespace Application\Identity\Port;

use Domain\Identity\Enum\Role;
use Domain\Identity\ValueObject\UserId;

/**
 * The role of a user, which is all an authenticated request needs to know about who sent it.
 */
interface UserRoles
{
    /**
     * @return ?Role null when there is no such user
     */
    public function roleOf(UserId $id): ?Role;
}
