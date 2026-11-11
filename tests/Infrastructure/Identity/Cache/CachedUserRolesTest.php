<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Identity\Cache;

use Domain\Identity\Enum\Role;
use Domain\Identity\ValueObject\UserId;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Identity\Cache\CachedUserRoles;
use Infrastructure\Identity\Persistence\EloquentUserRepository;
use Infrastructure\Identity\Persistence\EloquentUserRoles;
use Infrastructure\Identity\Persistence\UserModel;
use Tests\Support\Identity\Fixture\IdentityMother as Mother;
use Tests\Support\LaravelTestCase;

/**
 * Runs against the real Redis store, so what is cached must also survive serialization.
 */
final class CachedUserRolesTest extends LaravelTestCase
{
    use RefreshDatabase;

    private const string REDIS_STORE = 'redis';

    private const int TTL_SECONDS = 60;

    private CachedUserRoles $roles;

    protected function setUp(): void
    {
        parent::setUp();
        $cache = $this->redisCache();
        $cache->clear();
        $this->roles = new CachedUserRoles(
            roles: new EloquentUserRoles(),
            cache: $cache,
            ttlSeconds: self::TTL_SECONDS,
        );
    }

    public function test_ReadsTheRoleOfAStoredUser(): void
    {
        $users = new EloquentUserRepository();
        $users->add(user: Mother::user());
        $users->add(user: Mother::admin());

        $userRole = $this->roles->roleOf(id: UserId::fromString(id: Mother::USER_ID));
        $adminRole = $this->roles->roleOf(id: UserId::fromString(id: Mother::ADMIN_ID));

        self::assertSame(Role::User, $userRole);
        self::assertSame(Role::Admin, $adminRole);
    }

    public function test_TheRoleIsServedFromTheCacheOnceRead(): void
    {
        $admin = Mother::admin();
        (new EloquentUserRepository())->add(user: $admin);
        $this->roles->roleOf(id: $admin->id);
        UserModel::query()
            ->delete();

        $role = $this->roles->roleOf(id: $admin->id);

        self::assertSame(Role::Admin, $role);
    }

    public function test_AnUnknownUserIsNotCached(): void
    {
        $user = Mother::user();
        $missingRole = $this->roles->roleOf(id: $user->id);
        (new EloquentUserRepository())->add(user: $user);

        $role = $this->roles->roleOf(id: $user->id);

        self::assertNull($missingRole);
        self::assertSame(Role::User, $role);
    }

    private function redisCache(): Cache
    {
        return $this->app->make(CacheManager::class)
            ->store(self::REDIS_STORE);
    }
}
