<?php

declare(strict_types=1);

namespace Application\Identity\Port;

use Domain\Identity\ValueObject\PasswordHash;
use Domain\Identity\ValueObject\PlainPassword;
use SensitiveParameter;

interface PasswordHasher
{
    public function hash(PlainPassword $password): PasswordHash;

    /**
     * Without a hash (unknown email) it still spends the time of a real check and returns false,
     * so response time does not reveal which emails exist.
     */
    public function verify(#[SensitiveParameter] string $password, ?PasswordHash $hash): bool;
}
