<?php

declare(strict_types=1);

namespace Interfaces\Http\Identity;

use Application\Identity\Exception\InvalidAccessToken;
use Domain\Identity\Exception\EmailAlreadyRegistered;
use Domain\Identity\Exception\InvalidCredentials;
use Domain\Identity\Exception\InvalidEmail;
use Domain\Identity\Exception\InvalidPassword;
use Domain\Identity\Exception\InvalidRefreshToken;
use Domain\Identity\Exception\InvalidUserName;
use Domain\Identity\Exception\UserNotFound;
use Illuminate\Auth\AuthManager;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Interfaces\Http\Identity\Guard\BearerTokenAuthenticator;
use Interfaces\Http\Shared\Auth\AuthenticatedUser;
use Interfaces\Http\Shared\Problem\ProblemDetailsRenderer;
use Interfaces\Http\Shared\Problem\ProblemType;
use Interfaces\Http\Shared\Problem\ProblemTypeRegistry;
use Interfaces\Http\Shared\SharedHttpServiceProvider;

final class IdentityHttpServiceProvider extends ServiceProvider
{
    public const string GUARD_DRIVER = 'jwt';

    public const string LOGIN_RATE_LIMIT = 'identity-login';

    public const string REFRESH_RATE_LIMIT = 'identity-refresh';

    private const int DEFAULT_ATTEMPTS_PER_MINUTE = 5;

    public function boot(AuthManager $auth, RateLimiter $rateLimiter, ProblemTypeRegistry $problemTypes, Config $config): void
    {
        $auth->viaRequest(
            self::GUARD_DRIVER,
            function (Request $request): ?AuthenticatedUser {
                $authenticator = $this->app->make(BearerTokenAuthenticator::class);

                return $authenticator(request: $request);
            },
        );

        $configuredAttempts = $config->get('identity.rate_limit.attempts_per_minute');
        $attemptsPerMinute = is_numeric($configuredAttempts) ? (int) $configuredAttempts : self::DEFAULT_ATTEMPTS_PER_MINUTE;
        $rateLimiter->for(
            self::LOGIN_RATE_LIMIT,
            static function (Request $request) use ($attemptsPerMinute): Limit {
                $email = $request->string('email')
                    ->lower()
                    ->toString();

                return Limit::perMinute($attemptsPerMinute)
                    ->by($request->ip() . '|' . $email);
            },
        );
        $rateLimiter->for(
            self::REFRESH_RATE_LIMIT,
            static fn(Request $request): Limit => Limit::perMinute($attemptsPerMinute)
                ->by((string) $request->ip()),
        );

        $this->registerProblemTypes(problemTypes: $problemTypes);

        Route::middleware('api')
            ->group(base_path('routes/identity.php'));
    }

    private function registerProblemTypes(ProblemTypeRegistry $problemTypes): void
    {
        $invalidInput = new ProblemType(
            status: 422,
            type: ProblemDetailsRenderer::VALIDATION_FAILED_TYPE,
            title: 'The request is invalid.',
        );

        $problemTypes->register(exceptionClass: InvalidEmail::class, problemType: $invalidInput);
        $problemTypes->register(exceptionClass: InvalidUserName::class, problemType: $invalidInput);
        $problemTypes->register(exceptionClass: InvalidPassword::class, problemType: $invalidInput);
        $problemTypes->register(
            exceptionClass: EmailAlreadyRegistered::class,
            problemType: new ProblemType(
                status: 409,
                type: '/problems/email-already-registered',
                title: 'The email is already registered.',
            ),
        );
        $problemTypes->register(
            exceptionClass: InvalidCredentials::class,
            problemType: new ProblemType(
                status: 401,
                type: '/problems/invalid-credentials',
                title: 'The email or password is incorrect.',
            ),
        );
        $problemTypes->register(
            exceptionClass: InvalidRefreshToken::class,
            problemType: new ProblemType(
                status: 401,
                type: '/problems/invalid-refresh-token',
                title: 'The refresh token is not valid.',
            ),
        );
        $problemTypes->register(
            exceptionClass: InvalidAccessToken::class,
            problemType: new ProblemType(
                status: 401,
                type: '/problems/unauthenticated',
                title: 'Authentication is required.',
                isDetailFromException: false,
                headers: SharedHttpServiceProvider::BEARER_CHALLENGE,
            ),
        );
        $problemTypes->register(
            exceptionClass: UserNotFound::class,
            problemType: new ProblemType(
                status: 404,
                type: '/problems/not-found',
                title: 'The resource was not found.',
                isDetailFromException: false,
            ),
        );
    }
}
