<?php

declare(strict_types=1);

namespace Tests\Support\Identity\Port;

use Application\Identity\Port\OpaqueTokenGenerator;

final class SequentialOpaqueTokenGenerator implements OpaqueTokenGenerator
{
    private const string TOKEN_PREFIX = 'refresh-token-';

    private int $lastSequence = 0;

    /**
     * @return non-empty-string
     */
    public static function nth(int $sequence): string
    {
        return self::TOKEN_PREFIX . $sequence;
    }

    public function generate(): string
    {
        $this->lastSequence++;

        return self::nth(sequence: $this->lastSequence);
    }
}
