<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Shared\Middleware;

use Interfaces\Http\Shared\Middleware\RecordRequestMetrics;
use OpenTelemetry\SDK\Metrics\Data\HistogramDataPoint;
use Tests\Support\LaravelTestCase;
use Tests\Support\Shared\Observability\CapturesTelemetry;

final class RecordRequestMetricsTest extends LaravelTestCase
{
    use CapturesTelemetry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->captureTelemetry();
    }

    protected function tearDown(): void
    {
        $this->releaseTelemetry();
        parent::tearDown();
    }

    public function test_RecordsTheDurationByRouteTemplateMethodAndStatus(): void
    {
        $this->getJson('/health/live');
        $this->getJson('/no-such-route');

        $points = $this->histogramPoints();

        self::assertSame(1, $points['GET /health/live 200']->count ?? null);
        self::assertSame(1, $points['GET unmatched 404']->count ?? null);
        self::assertGreaterThan(0, $points['GET /health/live 200']->sum ?? 0);
    }

    public function test_TheBucketsAreFineEnoughForTheHighPercentiles(): void
    {
        $this->getJson('/health/live');

        $point = $this->histogramPoints()['GET /health/live 200'] ?? null;

        self::assertNotNull($point);
        self::assertSame(
            [0.005, 0.01, 0.025, 0.05, 0.075, 0.1, 0.25, 0.5, 0.75, 1.0, 2.5, 5.0, 7.5, 10.0],
            $point->explicitBounds,
        );
    }

    /**
     * @return array<string, HistogramDataPoint> keyed by "method route status"
     */
    private function histogramPoints(): array
    {
        $points = [];

        foreach ($this->capturedPoints(metricName: RecordRequestMetrics::INSTRUMENT) as $point) {
            \assert($point instanceof HistogramDataPoint);
            $attributes = $point->attributes->toArray();
            \assert(\is_string($attributes['http.request.method']) && \is_string($attributes['http.route']));
            \assert(\is_int($attributes['http.response.status_code']));
            $key = \sprintf(
                '%s %s %d',
                $attributes['http.request.method'],
                $attributes['http.route'],
                $attributes['http.response.status_code'],
            );
            $points[$key] = $point;
        }

        return $points;
    }
}
