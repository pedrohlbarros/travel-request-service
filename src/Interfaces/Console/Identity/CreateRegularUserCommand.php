<?php

declare(strict_types=1);

namespace Interfaces\Console\Identity;

use Domain\Identity\Enum\Role;

final class CreateRegularUserCommand extends CreateUserCommand
{
    protected $signature = 'identity:create-user {--name=} {--email=} {--password=}';

    protected $description = 'Create a regular user';

    protected function role(): Role
    {
        return Role::User;
    }
}
