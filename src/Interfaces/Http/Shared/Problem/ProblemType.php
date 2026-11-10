<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\Problem;

final readonly class ProblemType
{
    /**
     * @param bool $isDetailFromException false when the exception message would leak something, e.g. that an id exists
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $status,
        public string $type,
        public string $title,
        public bool $isDetailFromException = true,
        public array $headers = [],
    ) {}
}
