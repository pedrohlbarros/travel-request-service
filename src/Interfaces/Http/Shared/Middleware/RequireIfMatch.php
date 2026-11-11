<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Interfaces\Http\Shared\Concurrency\EntityTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;

/**
 * Writes must say which version they were based on, so a lost update can be detected.
 */
final readonly class RequireIfMatch
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasHeader(EntityTag::IF_MATCH_HEADER)) {
            throw new PreconditionRequiredHttpException('This request needs an If-Match header with the ETag of the resource.');
        }

        return $next($request);
    }
}
