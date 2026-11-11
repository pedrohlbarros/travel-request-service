<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Observability;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use OpenTelemetry\API\Trace\Span;

/**
 * Puts the current trace and span ids on every log line, so a log leads to its trace.
 */
final readonly class TraceContextLogProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $spanContext = Span::getCurrent()
            ->getContext();

        if (! $spanContext->isValid()) {
            return $record;
        }

        return $record->with(extra: [
            ...$record->extra,
            'trace_id' => $spanContext->getTraceId(),
            'span_id' => $spanContext->getSpanId(),
        ]);
    }
}
