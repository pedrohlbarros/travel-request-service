<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Cache;

use Application\Identity\Port\UserRoles;
use Domain\Identity\Enum\Role;
use Domain\Identity\ValueObject\UserId;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * A role is set when the user is created and nothing changes it afterwards, so nothing has to drop the
 * entry; the TTL bounds a change made straight in the database. Unknown users are not cached, so a user
 * can authenticate as soon as it exists.
 */
final readonly class CachedUserRoles implements UserRoles
{
    private const string KEY_PREFIX = 'identity:user-role:';

    /**
     * @param positive-int $ttlSeconds
     */
    public function __construct(
        private UserRoles $roles,
        private Cache $cache,
        private int $ttlSeconds,
    ) {}

    public function roleOf(UserId $id): ?Role
    {
        $key = self::KEY_PREFIX . $id->toString();
        $cachedRole = $this->cache->get($key);

        if (\is_string($cachedRole)) {
            return Role::from(value: $cachedRole);
        }

        $role = $this->roles->roleOf(id: $id);

        if ($role !== null) {
            $this->cache->put($key, $role->value, $this->ttlSeconds);
        }

        return $role;
    }
}
