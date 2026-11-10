<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\Auth;

use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who is calling, as every context sees it. Built per request by the access token guard.
 */
final readonly class AuthenticatedUser implements Authenticatable
{
    private const string IDENTIFIER_NAME = 'id';

    public function __construct(
        public string $id,
        public bool $isAdmin,
        public string $accessTokenId,
        public DateTimeImmutable $accessTokenExpiresAt,
    ) {}

    public function getAuthIdentifierName(): string
    {
        return self::IDENTIFIER_NAME;
    }

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return '';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
