<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use Application\Identity\Input\CreateUserInput;
use Application\Identity\Input\LoginInput;
use Application\Identity\Output\TokenPairOutput;
use Application\Identity\Output\UserOutput;
use Application\Identity\UseCase\CreateUserUseCase;
use Application\Identity\UseCase\LoginUseCase;
use Domain\Identity\Enum\Role;

/**
 * For HTTP tests: creates users and logs them in through the real use cases.
 */
trait AuthenticatesUsers
{
    protected const string TEST_PASSWORD = 'password123';

    protected function createUser(string $email, Role $role = Role::User, string $name = 'Nico Robin'): UserOutput
    {
        $newUser = new CreateUserInput(
            name: $name,
            email: $email,
            password: self::TEST_PASSWORD,
            role: $role,
        );

        return $this->app->make(CreateUserUseCase::class)
            ->execute(input: $newUser);
    }

    protected function loginAs(string $email): TokenPairOutput
    {
        $login = new LoginInput(email: $email, password: self::TEST_PASSWORD);

        return $this->app->make(LoginUseCase::class)
            ->execute(input: $login);
    }

    /**
     * @return array<string, string>
     */
    protected function bearer(string $accessToken): array
    {
        return ['Authorization' => 'Bearer ' . $accessToken, 'Accept' => 'application/json'];
    }
}
