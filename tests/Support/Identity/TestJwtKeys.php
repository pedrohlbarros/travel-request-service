<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use Illuminate\Contracts\Config\Repository as Config;
use RuntimeException;

/**
 * RSA key pairs for tests, generated once per test process in a temporary directory, so tests never
 * depend on (or overwrite) the developer's keys in storage/keys.
 */
final class TestJwtKeys
{
    public const string SIGNING_PAIR = 'signing';

    public const string FOREIGN_PAIR = 'foreign';

    private const int RSA_KEY_BITS = 2048;

    /** @var array<string, array{private: string, public: string}> */
    private static array $pathsByPairName = [];

    public static function useSigningPair(Config $config): void
    {
        $paths = self::paths(pairName: self::SIGNING_PAIR);
        $config->set('identity.jwt.private_key_path', $paths['private']);
        $config->set('identity.jwt.public_key_path', $paths['public']);
    }

    /**
     * @return array{private: string, public: string}
     */
    public static function paths(string $pairName): array
    {
        return self::$pathsByPairName[$pairName] ??= self::generate(pairName: $pairName);
    }

    /**
     * @return array{private: string, public: string}
     */
    private static function generate(string $pairName): array
    {
        $directory = sys_get_temp_dir() . '/travel-request-test-keys-' . getmypid();

        if (! is_dir($directory) && ! mkdir($directory, 0o700, true) && ! is_dir($directory)) {
            throw new RuntimeException(\sprintf('Could not create "%s".', $directory));
        }

        $privateKey = openssl_pkey_new(['private_key_bits' => self::RSA_KEY_BITS, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $privateKeyPem = null;

        if ($privateKey === false || ! openssl_pkey_export(
            $privateKey,
            $privateKeyPem,
        ) || ! \is_string($privateKeyPem)) {
            throw new RuntimeException('OpenSSL could not generate a test key pair.');
        }

        $publicKeyPem = openssl_pkey_get_details($privateKey)['key'] ?? null;

        if (! \is_string($publicKeyPem)) {
            throw new RuntimeException('OpenSSL could not export the test public key.');
        }

        $paths = ['private' => "{$directory}/{$pairName}-private.pem", 'public' => "{$directory}/{$pairName}-public.pem"];
        file_put_contents($paths['private'], $privateKeyPem);
        file_put_contents($paths['public'], $publicKeyPem);

        return $paths;
    }
}
