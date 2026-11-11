<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Console;

use Illuminate\Console\Command;
use Infrastructure\Identity\Token\JwtKeyPair;
use Infrastructure\Identity\Token\JwtSettings;

final class GenerateJwtKeysCommand extends Command
{
    private const int RSA_KEY_BITS = 2048;

    private const int PRIVATE_KEY_PERMISSIONS = 0o600;

    private const int PUBLIC_KEY_PERMISSIONS = 0o644;

    private const int DIRECTORY_PERMISSIONS = 0o700;

    protected $signature = 'identity:keys:generate {--force : Replace keys that already exist}';

    protected $description = 'Generate the RSA key pair that signs access tokens';

    public function handle(JwtSettings $settings): int
    {
        $hasKeys = is_file($settings->privateKeyPath) && is_file($settings->publicKeyPath);

        if ($hasKeys && $this->option('force') !== true) {
            $this->components->info('JWT keys already exist; use --force to replace them.');

            return self::SUCCESS;
        }

        [$privateKeyPem, $publicKeyPem] = $this->newKeyPair();
        $this->write(
            path: $settings->privateKeyPath,
            contents: $privateKeyPem,
            permissions: self::PRIVATE_KEY_PERMISSIONS,
        );
        $this->write(
            path: $settings->publicKeyPath,
            contents: $publicKeyPem,
            permissions: self::PUBLIC_KEY_PERMISSIONS,
        );

        $keyId = JwtKeyPair::fromFiles(
            privateKeyPath: $settings->privateKeyPath,
            publicKeyPath: $settings->publicKeyPath,
        )->keyId;
        $this->components->info(\sprintf('JWT keys written (kid %s).', $keyId));

        return self::SUCCESS;
    }

    /**
     * @return array{string, string}
     */
    private function newKeyPair(): array
    {
        // OpenSSL reports failures as warnings, which the framework raises as ErrorException,
        // so the asserts only narrow types.
        $privateKey = openssl_pkey_new(['private_key_bits' => self::RSA_KEY_BITS, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        \assert($privateKey !== false);
        $privateKeyPem = null;
        openssl_pkey_export($privateKey, $privateKeyPem);
        $keyDetails = openssl_pkey_get_details($privateKey);
        \assert($keyDetails !== false);
        $publicKeyPem = $keyDetails['key'] ?? null;
        \assert(\is_string($privateKeyPem) && \is_string($publicKeyPem));

        return [$privateKeyPem, $publicKeyPem];
    }

    private function write(string $path, string $contents, int $permissions): void
    {
        $directory = \dirname($path);

        // The framework's error handler turns a failing filesystem call into an ErrorException.
        if (! is_dir($directory)) {
            mkdir($directory, self::DIRECTORY_PERMISSIONS, true);
        }

        file_put_contents($path, $contents);
        chmod($path, $permissions);
    }
}
