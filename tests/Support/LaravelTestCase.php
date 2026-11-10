<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Testing\PendingCommand;
use Tests\Support\Identity\TestJwtKeys;

abstract class LaravelTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestJwtKeys::useSigningPair(config: $this->app->make(Config::class));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function command(string $command, array $parameters = []): PendingCommand
    {
        $pendingCommand = $this->artisan($command, $parameters);
        \assert($pendingCommand instanceof PendingCommand);

        return $pendingCommand;
    }
}
