<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Port;

use Application\Shared\Port\IdentifierGenerator;

final class SequentialIdentifierGenerator implements IdentifierGenerator
{
    private const string UUID7_PREFIX = '019a6a00-0000-7000-8000-';

    private const int SEQUENCE_DIGITS = 12;

    private int $lastSequence = 0;

    /**
     * @return non-empty-string
     */
    public static function nth(int $sequence): string
    {
        return self::UUID7_PREFIX . str_pad(
            (string) $sequence,
            self::SEQUENCE_DIGITS,
            '0',
            STR_PAD_LEFT,
        );
    }

    public function uuid7(): string
    {
        $this->lastSequence++;

        return self::nth(sequence: $this->lastSequence);
    }
}
