<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObject;

use SensitiveParameter;

final readonly class PasswordHash
{
    public function __construct(
        #[SensitiveParameter]
        public string $value,
    ) {}
}
