<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Identity\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;
use Infrastructure\Identity\Token\JwtKeyPair;
use Tests\Support\LaravelTestCase;

final class GenerateJwtKeysCommandTest extends LaravelTestCase
{
    private const int PRIVATE_KEY_PERMISSIONS = 0o600;

    private string $keyDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keyDirectory = sys_get_temp_dir() . '/travel-request-key-command-' . bin2hex(random_bytes(4));
        $config = $this->app->make(Config::class);
        $config->set('identity.jwt.private_key_path', "{$this->keyDirectory}/private.pem");
        $config->set('identity.jwt.public_key_path', "{$this->keyDirectory}/public.pem");
    }

    protected function tearDown(): void
    {
        new Filesystem()->deleteDirectory($this->keyDirectory);
        parent::tearDown();
    }

    public function test_WritesAUsableKeyPairWithAPrivateKeyOnlyTheOwnerReads(): void
    {
        $exitCode = $this->command(command: 'identity:keys:generate')
            ->run();

        $keyId = $this->currentKeyId();
        $privateKeyPermissions = fileperms("{$this->keyDirectory}/private.pem") & 0o777;

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNotSame('', $keyId);
        self::assertSame(self::PRIVATE_KEY_PERMISSIONS, $privateKeyPermissions);
    }

    public function test_KeepsExistingKeysUnlessForced(): void
    {
        $this->command(command: 'identity:keys:generate')
            ->run();
        $originalKeyId = $this->currentKeyId();

        $rerunExitCode = $this->command(command: 'identity:keys:generate')
            ->expectsOutputToContain('already exist')
            ->run();
        $keyIdAfterRerun = $this->currentKeyId();
        $this->command(command: 'identity:keys:generate', parameters: ['--force' => true])
            ->run();
        $keyIdAfterForce = $this->currentKeyId();

        self::assertSame(Command::SUCCESS, $rerunExitCode);
        self::assertSame($originalKeyId, $keyIdAfterRerun);
        self::assertNotSame($originalKeyId, $keyIdAfterForce);
    }

    private function currentKeyId(): string
    {
        $keys = JwtKeyPair::fromFiles(
            privateKeyPath: "{$this->keyDirectory}/private.pem",
            publicKeyPath: "{$this->keyDirectory}/public.pem",
        );

        return $keys->keyId;
    }
}
