<?php

declare(strict_types=1);

return [
    'metric_export_interval_ms' => (int) env('OTEL_METRIC_EXPORT_INTERVAL', 10000),
];
