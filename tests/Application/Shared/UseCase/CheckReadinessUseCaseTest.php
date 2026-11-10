<?php

declare(strict_types=1);

namespace Tests\Application\Shared\UseCase;

use Application\Shared\UseCase\CheckReadinessUseCase;
use PHPUnit\Framework\TestCase;
use Tests\Support\Shared\Port\FixedHealthCheck;

final class CheckReadinessUseCaseTest extends TestCase
{
    public function test_IsReadyWhenEveryCheckIsHealthy(): void
    {
        $useCase = new CheckReadinessUseCase([
            FixedHealthCheck::healthy('mysql'),
            FixedHealthCheck::healthy('redis'),
        ]);

        $readiness = $useCase->execute();

        self::assertTrue($readiness->isReady());
        self::assertSame(['mysql' => true, 'redis' => true], $readiness->checks);
    }

    public function test_IsNotReadyWhenAnyCheckIsUnhealthy(): void
    {
        $useCase = new CheckReadinessUseCase([
            FixedHealthCheck::healthy('mysql'),
            FixedHealthCheck::unhealthy('rabbitmq'),
            FixedHealthCheck::healthy('redis'),
        ]);

        $readiness = $useCase->execute();

        self::assertFalse($readiness->isReady());
        self::assertSame(['mysql' => true, 'rabbitmq' => false, 'redis' => true], $readiness->checks);
    }

    public function test_IsReadyWithoutChecks(): void
    {
        $useCaseWithoutChecks = new CheckReadinessUseCase([]);

        $readiness = $useCaseWithoutChecks->execute();

        self::assertTrue($readiness->isReady());
        self::assertSame([], $readiness->checks);
    }
}
