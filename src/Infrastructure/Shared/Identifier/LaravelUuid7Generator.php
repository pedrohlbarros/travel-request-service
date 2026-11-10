<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Identifier;

use Application\Shared\Port\IdentifierGenerator;
use Illuminate\Support\Str;

final readonly class LaravelUuid7Generator implements IdentifierGenerator
{
    public function uuid7(): string
    {
        return Str::uuid7()
            ->toString();
    }
}
