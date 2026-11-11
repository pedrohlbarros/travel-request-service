<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use OpenTelemetry\API\Globals;
use Symfony\Component\HttpFoundation\Response;

/**
 * http.server.request.duration as the OpenTelemetry semantic conventions name it, with buckets fine enough
 * for p95 and p99. The route template, not the path, keeps the series few.
 */
final readonly class RecordRequestMetrics
{
    public const string INSTRUMENT = 'http.server.request.duration';

    private const string UNMATCHED_ROUTE = 'unmatched';

    private const int NANOSECONDS_PER_SECOND = 1_000_000_000;

    private const array BUCKET_BOUNDARIES_SECONDS = [
        0.005, 0.01, 0.025, 0.05, 0.075, 0.1, 0.25, 0.5, 0.75, 1.0, 2.5, 5.0, 7.5, 10.0,
    ];

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        $response = $next($request);

        $durationSeconds = (hrtime(true) - $startedAt) / self::NANOSECONDS_PER_SECOND;
        $route = $request->route();
        $histogram = Globals::meterProvider()
            ->getMeter('travel-request-service')
            ->createHistogram(
                self::INSTRUMENT,
                's',
                'Duration of HTTP server requests.',
                ['ExplicitBucketBoundaries' => self::BUCKET_BOUNDARIES_SECONDS],
            );
        $routeTemplate = $route instanceof Route ? '/' . ltrim($route->uri(), '/') : self::UNMATCHED_ROUTE;
        $attributes = [
            'http.request.method' => $request->getMethod(),
            'http.route' => $routeTemplate,
            'http.response.status_code' => $response->getStatusCode(),
        ];
        $histogram->record($durationSeconds, $attributes);

        return $response;
    }
}
