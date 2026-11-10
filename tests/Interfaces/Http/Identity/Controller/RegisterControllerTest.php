<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Identity\Controller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Identity\AuthenticatesUsers;
use Tests\Support\LaravelTestCase;

final class RegisterControllerTest extends LaravelTestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    private const string REGISTER = '/api/v1/auth/register';

    public function test_RegistersARegularUserWithoutReturningThePassword(): void
    {
        $response = $this->postJson(
            self::REGISTER,
            ['name' => 'Nami', 'email' => 'Nami@Example.com', 'password' => self::TEST_PASSWORD],
        );

        $storedPassword = DB::table('users')
            ->value('password');
        \assert(\is_string($storedPassword));

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Nami')
            ->assertJsonPath('data.email', 'nami@example.com')
            ->assertJsonPath('data.role', 'user')
            ->assertJsonMissingPath('data.password');
        self::assertStringStartsWith('$argon2id$', $storedPassword);
    }

    public function test_CannotRegisterAsAdmin(): void
    {
        $response = $this->postJson(
            self::REGISTER,
            ['name' => 'Nami', 'email' => 'nami@example.com', 'password' => self::TEST_PASSWORD, 'role' => 'admin'],
        );

        $response->assertCreated()
            ->assertJsonPath('data.role', 'user');
    }

    public function test_RegisteredEmailIsAConflict(): void
    {
        $this->createUser(email: 'nami@example.com');

        $response = $this->postJson(
            self::REGISTER,
            ['name' => 'Nami', 'email' => 'NAMI@example.com', 'password' => self::TEST_PASSWORD],
        );

        $response->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', '/problems/email-already-registered')
            ->assertJsonPath('status', 409);
    }

    public function test_InvalidInputListsEveryField(): void
    {
        $response = $this->postJson(self::REGISTER, ['name' => 'N', 'email' => 'not-an-email', 'password' => 'short']);

        $response->assertUnprocessable()
            ->assertJsonPath('type', '/problems/validation-failed')
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }
}
