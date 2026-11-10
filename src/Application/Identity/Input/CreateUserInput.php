<?php

declare(strict_types=1);

namespace Application\Identity\Input;

use Domain\Identity\Enum\Role;
use SensitiveParameter;

final readonly class CreateUserInput
{
    public function __construct(
        public string $name,
        public string $email,
        #[SensitiveParameter]
        public string $password,
        public Role $role,
    ) {}
}
