<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Uri;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Identity\TestJwtKeys;

abstract class LaravelTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestJwtKeys::useSigningPair(config: $this->app->make(Config::class));
    }

    /**
     * Each request authenticates on its own, as under Octane, instead of reusing the user of the previous one.
     *
     * @param string $method
     * @param Uri|string $uri
     * @param array<mixed> $parameters
     * @param array<mixed> $cookies
     * @param array<mixed> $files
     * @param array<mixed> $server
     * @param ?string $content
     *
     * @return TestResponse<Response>
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null): TestResponse
    {
        $this->app->make(AuthManager::class)
            ->forgetGuards();

        return parent::call(
            $method,
            $uri,
            $parameters,
            $cookies,
            $files,
            $server,
            $content,
        );
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
