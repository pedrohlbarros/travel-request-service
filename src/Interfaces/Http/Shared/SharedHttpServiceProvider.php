<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared;

use Domain\Shared\Exception\InvalidIdentifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\ServiceProvider;
use Interfaces\Http\Shared\Problem\ProblemType;
use Interfaces\Http\Shared\Problem\ProblemTypeRegistry;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;

final class SharedHttpServiceProvider extends ServiceProvider
{
    public const array BEARER_CHALLENGE = ['WWW-Authenticate' => 'Bearer'];

    public function register(): void
    {
        $this->app->singleton(ProblemTypeRegistry::class);
    }

    public function boot(ProblemTypeRegistry $problemTypes): void
    {
        $unauthenticated = new ProblemType(
            status: 401,
            type: '/problems/unauthenticated',
            title: 'Authentication is required.',
            isDetailFromException: false,
            headers: self::BEARER_CHALLENGE,
        );
        $forbidden = new ProblemType(
            status: 403,
            type: '/problems/forbidden',
            title: 'You are not allowed to do this.',
            isDetailFromException: false,
        );

        $problemTypes->register(exceptionClass: AuthenticationException::class, problemType: $unauthenticated);
        $problemTypes->register(exceptionClass: AuthorizationException::class, problemType: $forbidden);
        $problemTypes->register(exceptionClass: AccessDeniedHttpException::class, problemType: $forbidden);
        $notFound = new ProblemType(
            status: 404,
            type: '/problems/not-found',
            title: 'The resource was not found.',
            isDetailFromException: false,
        );

        $problemTypes->register(exceptionClass: NotFoundHttpException::class, problemType: $notFound);
        $problemTypes->register(exceptionClass: InvalidIdentifier::class, problemType: $notFound);
        $problemTypes->register(
            exceptionClass: PreconditionRequiredHttpException::class,
            problemType: new ProblemType(
                status: 428,
                type: '/problems/precondition-required',
                title: 'The request must be conditional.',
            ),
        );
        $problemTypes->register(
            exceptionClass: ThrottleRequestsException::class,
            problemType: new ProblemType(
                status: 429,
                type: '/problems/too-many-requests',
                title: 'Too many attempts; try again later.',
                isDetailFromException: false,
            ),
        );
    }
}
