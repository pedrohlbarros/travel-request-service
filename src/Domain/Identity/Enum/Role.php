<?php

declare(strict_types=1);

namespace Domain\Identity\Enum;

enum Role: string
{
    case User = 'user';
    case Admin = 'admin';
}
