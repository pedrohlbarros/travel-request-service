<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Identity\Seeder;

use Application\Identity\Input\LoginInput;
use Application\Identity\UseCase\LoginUseCase;
use Domain\Identity\Enum\Role;
use Domain\Identity\Repository\UserRepository;
use Domain\Identity\ValueObject\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Infrastructure\Identity\Seeder\DemoUsersSeeder;
use Tests\Support\LaravelTestCase;

final class DemoUsersSeederTest extends LaravelTestCase
{
    use RefreshDatabase;

    public function test_CreatesOneAdminAndTwoUsersThatCanLogIn(): void
    {
        $luffyLogin = new LoginInput(email: 'luffy@example.com', password: DemoUsersSeeder::DEMO_PASSWORD);

        $this->seed(DemoUsersSeeder::class);

        $tokens = $this->app->make(LoginUseCase::class)
            ->execute(input: $luffyLogin);

        self::assertSame(Role::Admin, $this->roleOf(email: 'admin@example.com'));
        self::assertSame(Role::User, $this->roleOf(email: 'luffy@example.com'));
        self::assertSame(Role::User, $this->roleOf(email: 'zoro@example.com'));
        self::assertNotSame('', $tokens->accessToken);
    }

    public function test_RunningTwiceKeepsThreeUsers(): void
    {
        $this->seed(DemoUsersSeeder::class);

        $this->seed(DemoUsersSeeder::class);

        self::assertSame(
            3,
            DB::table('users')
                ->count(),
        );
    }

    public function test_RefusesToRunInProduction(): void
    {
        $this->app->detectEnvironment(static fn(): string => 'production');

        $this->expectExceptionMessageIs('Demo users are never seeded in production.');

        $this->app->make(DemoUsersSeeder::class)
            ->setContainer($this->app)
            ->__invoke();
    }

    private function roleOf(string $email): ?Role
    {
        $user = $this->app->make(UserRepository::class)
            ->findByEmail(email: Email::fromString(address: $email));

        return $user?->role;
    }
}
