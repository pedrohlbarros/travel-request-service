<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan\Fixtures\ExplicitFlow;

use RuntimeException;

final class ConditionalThrows
{
    public function coalesceThrow(?string $name): string
    {
        return $name ?? throw new RuntimeException('missing');
    }

    public function ternaryThrow(int $count): int
    {
        return $count > 0 ? $count : throw new RuntimeException('empty');
    }

    public function nestedTernary(int $count): string
    {
        return $count > 1 ? 'many' : ($count === 1 ? 'one' : 'none');
    }

    public function allowed(?string $name, bool $isReady): string
    {
        if ($name === null) {
            throw new RuntimeException('missing');
        }

        return $isReady ? $name : ($name ?? 'default');
    }
}
