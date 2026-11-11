<?php

declare(strict_types=1);

namespace Tests\Support\TravelRequest\Fixture;

use Illuminate\Database\ConnectionInterface;
use Infrastructure\Shared\Persistence\BinaryUuid;

/**
 * Travel requests reference real users, so database tests first store the fixture users they name.
 */
trait StoresFixtureUsers
{
    protected function storeFixtureUsers(): void
    {
        $users = [];

        foreach (TravelRequestMother::USER_NAMES as $id => $name) {
            $users[] = [
                'id' => BinaryUuid::toBytes(uuid: $id),
                'name' => $name,
                'email' => strtolower(str_replace([' ', '.'], '', $name)) . '@example.com',
                'password' => 'not-a-real-hash',
                'role' => $id === TravelRequestMother::ADMIN_ID ? 'admin' : 'user',
                'created_at' => TravelRequestMother::NOW,
                'updated_at' => TravelRequestMother::NOW,
            ];
        }

        $this->app->make(ConnectionInterface::class)
            ->table('users')
            ->insert($users);
    }
}
