<?php

declare(strict_types=1);

namespace Application\Identity\Output;

final readonly class JsonWebKeySetOutput
{
    /**
     * @param list<array<string, string>> $keys
     */
    public function __construct(
        public array $keys,
    ) {}
}
