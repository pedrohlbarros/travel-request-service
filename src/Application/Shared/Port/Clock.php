<?php

declare(strict_types=1);

namespace Application\Shared\Port;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
