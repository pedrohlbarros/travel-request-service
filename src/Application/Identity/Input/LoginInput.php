<?php

declare(strict_types=1);

namespace Application\Identity\Input;

use SensitiveParameter;

final readonly class LoginInput
{
    public function __construct(
        public string $email,
        #[SensitiveParameter]
        public string $password,
    ) {}
}
