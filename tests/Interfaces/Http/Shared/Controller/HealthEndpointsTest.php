<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Shared\Controller;

use Application\Shared\UseCase\CheckReadinessUseCase;
use Infrastructure\Shared\SharedServiceProvider;
use Tests\Support\LaravelTestCase;
use Tests\Support\Shared\Port\FixedHealthCheck;

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
        $this->app->instance(CheckReadinessUseCase::class, new CheckReadinessUseCase([
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
