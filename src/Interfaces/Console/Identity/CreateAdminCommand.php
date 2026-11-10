<?php

declare(strict_types=1);

namespace Interfaces\Console\Identity;

use Domain\Identity\Enum\Role;

final class CreateAdminCommand extends CreateUserCommand
{
    protected $signature = 'identity:create-admin {--name=} {--email=} {--password=}';

    protected $description = 'Create an admin, the only way to get one';

    protected function role(): Role
    {
        return Role::Admin;
    }
}
