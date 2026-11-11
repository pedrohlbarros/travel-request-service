<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\Concurrency;

use Illuminate\Http\Request;

/**
 * Strong entity tags carrying the resource version, e.g. "3".
 */
final readonly class EntityTag
{
    public const string HEADER = 'ETag';

    public const string IF_MATCH_HEADER = 'If-Match';

    /**
     * Versions start at 1, so this one never matches: an If-Match that is weak, a list, "*" or malformed is
     * turned into a version mismatch, checked only after the resource is known to exist and to be visible.
     */
    public const int NEVER_MATCHING_VERSION = 0;

    private const string STRONG_VERSION_TAG = '/\A"(?<version>[1-9][0-9]{0,9})"\z/';

    public static function ofVersion(int $version): string
    {
        return '"' . $version . '"';
    }

    public static function expectedVersionOf(Request $request): int
    {
        $ifMatch = trim((string) $request->header(self::IF_MATCH_HEADER));

        if (preg_match(self::STRONG_VERSION_TAG, $ifMatch, $matches) !== 1) {
            return self::NEVER_MATCHING_VERSION;
        }

        return (int) $matches['version'];
    }
}
