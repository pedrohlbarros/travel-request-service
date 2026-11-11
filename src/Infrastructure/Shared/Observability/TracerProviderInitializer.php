<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Observability;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\SDK\Common\Util\ShutdownHandler;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\ExporterFactory;
use OpenTelemetry\SDK\Trace\SpanProcessorFactory;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * The SDK builds its providers from the OTEL_* settings, which cannot name a sampler of our own. This
 * initializer runs right after the SDK's and replaces only the tracer provider, keeping the exporter and
 * processor the settings describe.
 */
final readonly class TracerProviderInitializer
{
    public static function register(): void
    {
        Globals::registerInitializer(self::initialize(...));
    }

    public static function initialize(Configurator $configurator): Configurator
    {
        if (Sdk::isDisabled()) {
            return $configurator;
        }

        $exporter = new ExporterFactory()
            ->create();
        $tracerProvider = TracerProvider::builder()
            ->addSpanProcessor(new SpanProcessorFactory()->create($exporter))
            ->setResource(ResourceInfoFactory::defaultResource())
            ->setSampler(new HealthCheckSampler())
            ->build();
        ShutdownHandler::register($tracerProvider->shutdown(...));

        return $configurator->withTracerProvider($tracerProvider);
    }
}
