<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Observability;

use OpenTelemetry\API\Metrics\ObserverInterface;

/**
 * Memory of each process (Octane worker, relay, consumer), told apart by its service.instance.id.
 */
final readonly class ProcessMetrics
{
    public static function register(Telemetry $telemetry): void
    {
        $meter = $telemetry->meter();

        $meter->createObservableGauge('process.memory.usage', 'By', 'Memory the process holds from the system.')
            ->observe(static function (ObserverInterface $observer): void {
                $observer->observe(memory_get_usage(true));
            });
        $meter->createObservableGauge('process.memory.peak', 'By', 'Most memory the process has held.')
            ->observe(static function (ObserverInterface $observer): void {
                $observer->observe(memory_get_peak_usage(true));
            });
    }
}
