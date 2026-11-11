<?php

declare(strict_types=1);

namespace Application\TravelRequest\Output;

/**
 * A user as a travel request shows it: who asked for it or who decided it.
 */
final readonly class UserReferenceOutput
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
