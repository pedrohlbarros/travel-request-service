<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Seeder;

use Application\Identity\Input\CreateUserInput;
use Application\Identity\UseCase\CreateUserUseCase;
use Domain\Identity\Enum\Role;
use Domain\Identity\Exception\EmailAlreadyRegistered;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;
use RuntimeException;

final class DemoUsersSeeder extends Seeder
{
    public const string DEMO_PASSWORD = 'password123';

    /** @var array<string, array{name: string, role: Role}> */
    public const array DEMO_USER_BY_EMAIL = [
        'admin@example.com' => ['name' => 'Admin', 'role' => Role::Admin],
        'luffy@example.com' => ['name' => 'Monkey D. Luffy', 'role' => Role::User],
        'zoro@example.com' => ['name' => 'Roronoa Zoro', 'role' => Role::User],
    ];

    public function run(Application $app, CreateUserUseCase $createUser): void
    {
        if ($app->environment('production')) {
            throw new RuntimeException('Demo users are never seeded in production.');
        }

        foreach (self::DEMO_USER_BY_EMAIL as $email => $demoUser) {
            try {
                $createUser->execute(input: new CreateUserInput(
                    name: $demoUser['name'],
                    email: $email,
                    password: self::DEMO_PASSWORD,
                    role: $demoUser['role'],
                ));
                $this->command?->line(\sprintf('  Created %s (%s)', $email, $demoUser['role']->value));
            } catch (EmailAlreadyRegistered) {
                $this->command?->line(\sprintf('  Skipped %s: already registered', $email));
            }
        }
    }
}
