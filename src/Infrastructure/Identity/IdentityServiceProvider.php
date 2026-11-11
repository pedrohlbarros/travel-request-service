<?php

declare(strict_types=1);

namespace Infrastructure\Identity;

use Application\Identity\Config\TokenLifetimes;
use Application\Identity\Port\AccessTokenIssuer;
use Application\Identity\Port\AccessTokenRevocationList;
use Application\Identity\Port\AccessTokenVerifier;
use Application\Identity\Port\JsonWebKeySet;
use Application\Identity\Port\OpaqueTokenGenerator;
use Application\Identity\Port\PasswordHasher;
use Application\Identity\Port\UserRoles;
use Domain\Identity\Repository\RefreshTokenRepository;
use Domain\Identity\Repository\UserRepository;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Identity\Cache\CachedUserRoles;
use Infrastructure\Identity\Console\GenerateJwtKeysCommand;
use Infrastructure\Identity\Persistence\EloquentRefreshTokenRepository;
use Infrastructure\Identity\Persistence\EloquentUserRepository;
use Infrastructure\Identity\Persistence\EloquentUserRoles;
use Infrastructure\Identity\Security\LaravelPasswordHasher;
use Infrastructure\Identity\Token\JwtKeyPair;
use Infrastructure\Identity\Token\JwtSettings;
use Infrastructure\Identity\Token\LcobucciAccessTokens;
use Infrastructure\Identity\Token\RandomOpaqueTokenGenerator;
use Infrastructure\Identity\Token\RedisAccessTokenRevocationList;
use Infrastructure\Identity\Token\RsaJsonWebKeySet;
use Infrastructure\Shared\Config\TypedConfig;

final class IdentityServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserRepository::class => EloquentUserRepository::class,
        RefreshTokenRepository::class => EloquentRefreshTokenRepository::class,
        PasswordHasher::class => LaravelPasswordHasher::class,
        AccessTokenIssuer::class => LcobucciAccessTokens::class,
        AccessTokenVerifier::class => LcobucciAccessTokens::class,
        AccessTokenRevocationList::class => RedisAccessTokenRevocationList::class,
        OpaqueTokenGenerator::class => RandomOpaqueTokenGenerator::class,
        JsonWebKeySet::class => RsaJsonWebKeySet::class,
    ];

    public function register(): void
    {
        $this->app->singleton(
            JwtSettings::class,
            static function (Application $app): JwtSettings {
                $config = $app->make(Config::class);

                return new JwtSettings(
                    issuer: TypedConfig::nonEmptyString(config: $config, key: 'identity.jwt.issuer'),
                    audience: TypedConfig::nonEmptyString(config: $config, key: 'identity.jwt.audience'),
                    privateKeyPath: TypedConfig::nonEmptyString(config: $config, key: 'identity.jwt.private_key_path'),
                    publicKeyPath: TypedConfig::nonEmptyString(config: $config, key: 'identity.jwt.public_key_path'),
                );
            },
        );

        $this->app->singleton(
            JwtKeyPair::class,
            static function (Application $app): JwtKeyPair {
                $settings = $app->make(JwtSettings::class);

                return JwtKeyPair::fromFiles(
                    privateKeyPath: $settings->privateKeyPath,
                    publicKeyPath: $settings->publicKeyPath,
                );
            },
        );

        $this->app->bind(
            UserRoles::class,
            static function (Application $app): UserRoles {
                $config = $app->make(Config::class);

                return new CachedUserRoles(
                    roles: new EloquentUserRoles(),
                    cache: $app->make(Cache::class),
                    ttlSeconds: TypedConfig::positiveInt(config: $config, key: 'identity.role_cache_ttl'),
                );
            },
        );

        $this->app->singleton(
            TokenLifetimes::class,
            static function (Application $app): TokenLifetimes {
                $config = $app->make(Config::class);

                return new TokenLifetimes(
                    accessTokenSeconds: TypedConfig::positiveInt(config: $config, key: 'identity.jwt.access_token_ttl'),
                    refreshTokenSeconds: TypedConfig::positiveInt(config: $config, key: 'identity.refresh_token_ttl'),
                );
            },
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([GenerateJwtKeysCommand::class]);
        }
    }
}
