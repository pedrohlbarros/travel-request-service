<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Persistence;

use InvalidArgumentException;

/**
 * UUIDs are stored as BINARY(16) and exposed as their canonical string.
 */
final readonly class BinaryUuid
{
    private const int BYTES = 16;

    private const string CANONICAL_LAYOUT = '%s-%s-%s-%s-%s';

    public static function toBytes(string $uuid): string
    {
        $hex = str_replace('-', '', $uuid);
        $bytes = ctype_xdigit($hex) ? hex2bin($hex) : false;

        if ($bytes === false || \strlen($bytes) !== self::BYTES) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a UUID.', $uuid));
        }

        return $bytes;
    }

    public static function fromBytes(string $bytes): string
    {
        if (\strlen($bytes) !== self::BYTES) {
            throw new InvalidArgumentException('A binary UUID has exactly 16 bytes.');
        }

        $hex = bin2hex($bytes);

        return \sprintf(
            self::CANONICAL_LAYOUT,
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
