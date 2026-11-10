<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Token;

use Application\Identity\Port\AccessTokenRevocationList;
use Application\Shared\Port\Clock;
use DateTimeImmutable;
use Illuminate\Redis\RedisManager;

final readonly class RedisAccessTokenRevocationList implements AccessTokenRevocationList
{
    private const string KEY_PREFIX = 'identity:revoked-access-token:';

    private const int SHORTEST_TTL_SECONDS = 1;

    public function __construct(
        private RedisManager $redis,
        private Clock $clock,
    ) {}

    public function revoke(string $tokenId, DateTimeImmutable $until): void
    {
        $secondsUntilExpiry = $until->getTimestamp() - $this->clock->now()
            ->getTimestamp();

        $this->redis->connection()
            ->command(
                'set',
                [
                    self::KEY_PREFIX . $tokenId,
                    '1',
                    ['EX' => max(self::SHORTEST_TTL_SECONDS, $secondsUntilExpiry)],
                ],
            );
    }

    public function isRevoked(string $tokenId): bool
    {
        return (bool) $this->redis->connection()
            ->command('exists', [self::KEY_PREFIX . $tokenId]);
    }
}
