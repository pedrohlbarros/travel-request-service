<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Seeder;

use Application\Identity\Input\CreateUserInput;
use Application\Identity\UseCase\CreateUserUseCase;
use Domain\Identity\Enum\Role;
use Domain\Identity\Exception\EmailAlreadyRegistered;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;
use Infrastructure\Shared\Config\TypedConfig;
use RuntimeException;

/**
 * The users the load tests log in as: load-user-NN and load-admin-NN, all with one known password.
 */
final class LoadTestUsersSeeder extends Seeder
{
    public const string PASSWORD = 'load-password-123';

    public const string USER_EMAIL = 'load-user-%02d@example.com';

    public const string ADMIN_EMAIL = 'load-admin-%02d@example.com';

    public function run(Application $app, Config $config, CreateUserUseCase $createUser): void
    {
        if ($app->environment('production')) {
            throw new RuntimeException('Load test users are never seeded in production.');
        }

        $users = TypedConfig::positiveInt(config: $config, key: 'load_test.users');
        $admins = TypedConfig::positiveInt(config: $config, key: 'load_test.admins');
        $created = 0;

        for ($number = 1; $number <= $users; $number++) {
            $created += $this->create(
                createUser: $createUser,
                emailFormat: self::USER_EMAIL,
                number: $number,
                role: Role::User,
            );
        }

        for ($number = 1; $number <= $admins; $number++) {
            $created += $this->create(
                createUser: $createUser,
                emailFormat: self::ADMIN_EMAIL,
                number: $number,
                role: Role::Admin,
            );
        }

        $this->command?->line(\sprintf('  %d load test users created, the others already existed', $created));
    }

    /**
     * @return int 1 when the user was created, 0 when it already existed
     */
    private function create(CreateUserUseCase $createUser, string $emailFormat, int $number, Role $role): int
    {
        $email = \sprintf($emailFormat, $number);
        $name = \sprintf('Load %s %02d', $role->value, $number);

        try {
            $createUser->execute(input: new CreateUserInput(
                name: $name,
                email: $email,
                password: self::PASSWORD,
                role: $role,
            ));
        } catch (EmailAlreadyRegistered) {
            return 0;
        }

        return 1;
    }
}
