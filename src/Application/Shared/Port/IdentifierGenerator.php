<?php

declare(strict_types=1);

namespace Application\Shared\Port;

interface IdentifierGenerator
{
    /**
     * @return non-empty-string a canonical UUIDv7
     */
    public function uuid7(): string;
}
