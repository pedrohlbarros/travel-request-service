<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Token;

use RuntimeException;

/**
 * The RSA key pair read from disk, plus its RFC 7638 thumbprint used as `kid`.
 * Immutable once loaded, so one instance can serve every request of a worker.
 */
final readonly class JwtKeyPair
{
    private const string THUMBPRINT_ALGORITHM = 'sha256';

    /**
     * @param non-empty-string $privateKeyPem
     * @param non-empty-string $publicKeyPem
     */
    private function __construct(
        public string $privateKeyPem,
        public string $publicKeyPem,
        public string $modulus,
        public string $exponent,
        public string $keyId,
    ) {}

    public static function fromFiles(string $privateKeyPath, string $publicKeyPath): self
    {
        $privateKeyPem = self::read(path: $privateKeyPath);
        $publicKeyPem = self::read(path: $publicKeyPath);
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        $rsaDetails = $publicKey === false ? null : (openssl_pkey_get_details($publicKey)['rsa'] ?? null);

        if (! \is_array($rsaDetails) || ! \is_string($rsaDetails['n'] ?? null) || ! \is_string($rsaDetails['e'] ?? null)) {
            throw new RuntimeException(\sprintf('"%s" is not an RSA public key.', $publicKeyPath));
        }

        $modulus = Base64Url::encode(bytes: $rsaDetails['n']);
        $exponent = Base64Url::encode(bytes: $rsaDetails['e']);

        return new self(
            privateKeyPem: $privateKeyPem,
            publicKeyPem: $publicKeyPem,
            modulus: $modulus,
            exponent: $exponent,
            keyId: self::thumbprint(modulus: $modulus, exponent: $exponent),
        );
    }

    /**
     * @return non-empty-string
     */
    private static function read(string $path): string
    {
        $contents = is_readable($path) ? file_get_contents($path) : false;

        if ($contents === false || $contents === '') {
            throw new RuntimeException(\sprintf('JWT key "%s" is missing; run `make keys`.', $path));
        }

        return $contents;
    }

    /**
     * RFC 7638: SHA-256 over the required members in lexicographic order, without whitespace.
     */
    private static function thumbprint(string $modulus, string $exponent): string
    {
        $canonicalJwk = json_encode(
            ['e' => $exponent, 'kty' => 'RSA', 'n' => $modulus],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );

        return Base64Url::encode(bytes: hash(self::THUMBPRINT_ALGORITHM, $canonicalJwk, true));
    }
}
