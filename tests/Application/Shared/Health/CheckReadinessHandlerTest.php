<?php

declare(strict_types=1);

namespace Tests\Application\Shared\Health;

use Application\Shared\Health\CheckReadiness;
use Application\Shared\Health\CheckReadinessHandler;
use PHPUnit\Framework\TestCase;
use Tests\Support\Shared\Health\FixedHealthCheck;

final class CheckReadinessHandlerTest extends TestCase
{
    public function test_IsReadyWhenEveryCheckIsHealthy(): void
    {
        $handler = new CheckReadinessHandler([
            FixedHealthCheck::healthy('mysql'),
            FixedHealthCheck::healthy('redis'),
        ]);

        $report = $handler->handle(new CheckReadiness());

        self::assertTrue($report->isReady());
        self::assertSame(['mysql' => true, 'redis' => true], $report->checks);
    }

    public function test_IsNotReadyWhenAnyCheckIsUnhealthy(): void
    {
        $handler = new CheckReadinessHandler([
            FixedHealthCheck::healthy('mysql'),
            FixedHealthCheck::unhealthy('rabbitmq'),
            FixedHealthCheck::healthy('redis'),
        ]);

        $report = $handler->handle(new CheckReadiness());

        self::assertFalse($report->isReady());
        self::assertSame(['mysql' => true, 'rabbitmq' => false, 'redis' => true], $report->checks);
    }

    public function test_IsReadyWithoutChecks(): void
    {
        $handlerWithoutChecks = new CheckReadinessHandler([]);

        $report = $handlerWithoutChecks->handle(new CheckReadiness());

        self::assertTrue($report->isReady());
        self::assertSame([], $report->checks);
    }
}
