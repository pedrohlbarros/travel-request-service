<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Token;

final readonly class Base64Url
{
    public static function encode(string $bytes): string
    {
        $base64 = base64_encode($bytes);
        $urlSafeBase64 = strtr($base64, '+/', '-_');

        return rtrim($urlSafeBase64, '=');
    }
}
