<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Identity\Seeder;

use Application\Identity\Input\LoginInput;
use Application\Identity\UseCase\LoginUseCase;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Identity\Seeder\LoadTestUsersSeeder;
use Tests\Support\LaravelTestCase;

final class LoadTestUsersSeederTest extends LaravelTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->app->make(Config::class);
        $config->set('load_test.users', 2);
        $config->set('load_test.admins', 1);
    }

    public function test_CreatesUsersAndAdminsThatCanLogIn(): void
    {
        $this->seed(LoadTestUsersSeeder::class);

        $adminLogin = new LoginInput(email: 'load-admin-01@example.com', password: LoadTestUsersSeeder::PASSWORD);
        $tokens = $this->app->make(LoginUseCase::class)
            ->execute(input: $adminLogin);
        $roleByEmail = $this->app->make(ConnectionInterface::class)
            ->table('users')
            ->orderBy('email')
            ->pluck('role', 'email')
            ->all();

        self::assertSame(
            [
                'load-admin-01@example.com' => 'admin',
                'load-user-01@example.com' => 'user',
                'load-user-02@example.com' => 'user',
            ],
            $roleByEmail,
        );
        self::assertNotSame('', $tokens->accessToken);
    }

    public function test_RunningTwiceCreatesNobodyNew(): void
    {
        $this->seed(LoadTestUsersSeeder::class);

        $this->seed(LoadTestUsersSeeder::class);

        $users = $this->app->make(ConnectionInterface::class)
            ->table('users')
            ->count();

        self::assertSame(3, $users);
    }

    public function test_RefusesToRunInProduction(): void
    {
        $this->app->detectEnvironment(static fn(): string => 'production');

        $this->expectExceptionMessageIs('Load test users are never seeded in production.');

        $this->app->make(LoadTestUsersSeeder::class)
            ->setContainer($this->app)
            ->__invoke();
    }
}
