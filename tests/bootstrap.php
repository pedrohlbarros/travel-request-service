<?php

declare(strict_types=1);

$testDatabase = getenv('DB_TEST_DATABASE');
$testDatabase = \is_string($testDatabase) && $testDatabase !== '' ? $testDatabase : 'travel_request_testing';

putenv("DB_DATABASE={$testDatabase}");
$_ENV['DB_DATABASE'] = $testDatabase;
$_SERVER['DB_DATABASE'] = $testDatabase;

require __DIR__ . '/../vendor/autoload.php';

OpenTelemetry\API\Globals::registerInitializer(
    static fn(OpenTelemetry\API\Instrumentation\Configurator $configurator): OpenTelemetry\API\Instrumentation\Configurator => $configurator
        ->withTracerProvider(Tests\Support\Shared\Observability\SwitchableTracerProvider::instance()),
);
