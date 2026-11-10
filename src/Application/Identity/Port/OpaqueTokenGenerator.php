<?php

declare(strict_types=1);

namespace Application\Identity\Port;

interface OpaqueTokenGenerator
{
    /**
     * @return non-empty-string an unguessable, URL-safe token
     */
    public function generate(): string;
}
