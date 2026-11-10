<?php

declare(strict_types=1);

namespace Application\Identity\Port;

use Application\Identity\Exception\InvalidAccessToken;
use DateTimeImmutable;

interface AccessTokenVerifier
{
    /**
     * @throws InvalidAccessToken
     */
    public function verify(string $token, DateTimeImmutable $now): VerifiedAccessToken;
}
