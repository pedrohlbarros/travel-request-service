<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use Infrastructure\Identity\Token\Base64Url;
use RuntimeException;

/**
 * Reads and forges the base64url segments of a JWT, for tests that look inside tokens.
 */
final readonly class JwtSegments
{
    private const int HEADER = 0;

    private const int CLAIMS = 1;

    private const int SIGNATURE = 2;

    /**
     * @return array<string, mixed>
     */
    public static function header(string $token): array
    {
        return self::decodedSegment(token: $token, position: self::HEADER);
    }

    /**
     * @return array<string, mixed>
     */
    public static function claims(string $token): array
    {
        return self::decodedSegment(token: $token, position: self::CLAIMS);
    }

    /**
     * @param array<string, mixed> $forgedClaims
     */
    public static function withClaims(string $token, array $forgedClaims): string
    {
        $segments = explode('.', $token);
        $encodedClaims = json_encode($forgedClaims, JSON_THROW_ON_ERROR);
        $segments[self::CLAIMS] = Base64Url::encode(bytes: $encodedClaims);

        return implode('.', $segments);
    }

    public static function signatureOf(string $token): string
    {
        $segments = explode('.', $token);

        return $segments[self::SIGNATURE];
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodedSegment(string $token, int $position): array
    {
        $segments = explode('.', $token);
        $base64 = strtr($segments[$position], '-_', '+/');
        $json = (string) base64_decode($base64, true);
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! \is_array($decoded)) {
            throw new RuntimeException('The JWT segment is not a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
