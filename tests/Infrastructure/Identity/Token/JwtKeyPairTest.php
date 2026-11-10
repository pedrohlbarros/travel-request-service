<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Identity\Token;

use Infrastructure\Identity\Token\Base64Url;
use Infrastructure\Identity\Token\JwtKeyPair;
use Infrastructure\Identity\Token\RsaJsonWebKeySet;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Identity\TestJwtKeys;

final class JwtKeyPairTest extends TestCase
{
    private const string RSA_PUBLIC_EXPONENT = 'AQAB';

    private const string THUMBPRINT_ALGORITHM = 'sha256';

    public function test_KeyIdIsTheRfc7638ThumbprintOfThePublicKey(): void
    {
        $modulus = $this->signingModulus();
        $canonicalJwk = '{"e":"AQAB","kty":"RSA","n":"' . $modulus . '"}';
        $thumbprintBytes = hash(self::THUMBPRINT_ALGORITHM, $canonicalJwk, true);

        $keys = $this->signingKeys();

        self::assertSame(Base64Url::encode(bytes: $thumbprintBytes), $keys->keyId);
        self::assertSame($modulus, $keys->modulus);
        self::assertSame(self::RSA_PUBLIC_EXPONENT, $keys->exponent);
    }

    public function test_DifferentKeysHaveDifferentKeyIds(): void
    {
        $foreignPaths = TestJwtKeys::paths(pairName: TestJwtKeys::FOREIGN_PAIR);

        $foreignKeys = JwtKeyPair::fromFiles(
            privateKeyPath: $foreignPaths['private'],
            publicKeyPath: $foreignPaths['public'],
        );

        self::assertNotSame($this->signingKeys()->keyId, $foreignKeys->keyId);
    }

    public function test_FailsClearlyWhenAKeyFileIsMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('run `make keys`');

        JwtKeyPair::fromFiles(privateKeyPath: '/nonexistent/private.pem', publicKeyPath: '/nonexistent/public.pem');
    }

    public function test_KeySetPublishesOnlyThePublicPart(): void
    {
        $keys = $this->signingKeys();

        $publicKeys = new RsaJsonWebKeySet(keys: $keys)->publicKeys();

        self::assertSame(
            [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => $keys->keyId,
                'n' => $keys->modulus,
                'e' => self::RSA_PUBLIC_EXPONENT,
            ]],
            $publicKeys,
        );
    }

    private function signingKeys(): JwtKeyPair
    {
        $paths = TestJwtKeys::paths(pairName: TestJwtKeys::SIGNING_PAIR);

        return JwtKeyPair::fromFiles(privateKeyPath: $paths['private'], publicKeyPath: $paths['public']);
    }

    /**
     * The modulus read straight from OpenSSL, independent of the class under test.
     */
    private function signingModulus(): string
    {
        $paths = TestJwtKeys::paths(pairName: TestJwtKeys::SIGNING_PAIR);
        $publicKeyPem = (string) file_get_contents($paths['public']);
        $publicKey = openssl_pkey_get_public($publicKeyPem);

        if ($publicKey === false) {
            throw new RuntimeException('The test public key cannot be read.');
        }

        $rsaDetails = openssl_pkey_get_details($publicKey)['rsa'] ?? null;
        $rawModulus = \is_array($rsaDetails) ? ($rsaDetails['n'] ?? null) : null;

        if (! \is_string($rawModulus)) {
            throw new RuntimeException('The test key is not RSA.');
        }

        return Base64Url::encode(bytes: $rawModulus);
    }
}
