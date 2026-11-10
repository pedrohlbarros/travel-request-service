<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Shared\Health;

use Application\Shared\Health\CheckReadinessHandler;
use Infrastructure\Shared\SharedServiceProvider;
use Tests\Support\LaravelTestCase;
use Tests\Support\Shared\Health\FixedHealthCheck;

final class HealthEndpointsTest extends LaravelTestCase
{
    public function test_LiveAnswersOk(): void
    {
        $response = $this->getJson('/health/live');

        $response->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_ReadyAnswersOkWhenEveryDependencyIsUp(): void
    {
        $response = $this->getJson('/health/ready');

        $response->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'checks' => ['mysql' => 'ok', 'redis' => 'ok', 'rabbitmq' => 'ok'],
            ]);
    }

    public function test_ReadyAnswersServiceUnavailableWhenAnyDependencyIsDown(): void
    {
        $this->app->instance(CheckReadinessHandler::class, new CheckReadinessHandler([
            FixedHealthCheck::healthy('mysql'),
            FixedHealthCheck::unhealthy('redis'),
        ]));

        $response = $this->getJson('/health/ready');

        $response->assertServiceUnavailable()
            ->assertExactJson([
                'status' => 'unavailable',
                'checks' => ['mysql' => 'ok', 'redis' => 'fail'],
            ]);
    }

    public function test_ReadyRunsEveryTaggedCheck(): void
    {
        $registeredChecks = iterator_to_array($this->app->tagged(SharedServiceProvider::HEALTH_CHECKS_TAG));

        self::assertCount(3, $registeredChecks);
    }
}
