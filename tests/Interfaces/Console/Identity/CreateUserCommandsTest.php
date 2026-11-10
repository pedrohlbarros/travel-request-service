<?php

declare(strict_types=1);

namespace Tests\Interfaces\Console\Identity;

use Domain\Identity\Enum\Role;
use Domain\Identity\Repository\UserRepository;
use Domain\Identity\ValueObject\Email;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LaravelTestCase;

final class CreateUserCommandsTest extends LaravelTestCase
{
    use RefreshDatabase;

    private const string PASSWORD = 'password123';

    public function test_CreatesAnAdminFromOptions(): void
    {
        $exitCode = $this->command(
            command: 'identity:create-admin',
            parameters: ['--name' => 'Nefertari Vivi', '--email' => 'vivi@example.com', '--password' => self::PASSWORD],
        )
            ->expectsOutputToContain('Created admin vivi@example.com')
            ->run();

        $storedRole = $this->storedRoleOf(email: 'vivi@example.com');

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(Role::Admin, $storedRole);
    }

    public function test_AsksForWhatIsMissingAndCreatesARegularUser(): void
    {
        $exitCode = $this->command(command: 'identity:create-user')
            ->expectsQuestion('Name', 'Tony Tony Chopper')
            ->expectsQuestion('Email', 'chopper@example.com')
            ->expectsQuestion('Password', self::PASSWORD)
            ->run();

        $storedRole = $this->storedRoleOf(email: 'chopper@example.com');

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(Role::User, $storedRole);
    }

    public function test_ReportsInvalidInputAndFails(): void
    {
        $this->command(
            command: 'identity:create-user',
            parameters: ['--name' => 'Usopp', '--email' => 'not-an-email', '--password' => self::PASSWORD],
        )
            ->expectsOutputToContain('not a valid email address')
            ->assertFailed();
    }

    private function storedRoleOf(string $email): ?Role
    {
        return $this->app->make(UserRepository::class)
            ->findByEmail(email: Email::fromString(address: $email))?->role;
    }
}
