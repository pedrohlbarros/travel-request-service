<?php

declare(strict_types=1);

namespace Tests\Domain\Identity\Enum;

use Domain\Identity\Enum\Role;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function test_RoleValuesAreTheApiContract(): void
    {
        $roleValues = array_map(static fn(Role $role): string => $role->value, Role::cases());

        self::assertSame(['user', 'admin'], $roleValues);
    }
}
