<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Notification\Messaging;

use Infrastructure\Notification\Messaging\DecisionConsumerSettings;
use PHPUnit\Framework\TestCase;

final class DecisionConsumerSettingsTest extends TestCase
{
    public function test_NamesOneWaitQueuePerRetryAfterItsDelay(): void
    {
        $settings = $this->settings(retryDelaysMilliseconds: [10_000, 60_000, 300_000, 1500]);

        self::assertSame(4, $settings->maxRetries());
        self::assertSame('notification.travel_request.retry.10s', $settings->retryQueue(retry: 1));
        self::assertSame('notification.travel_request.retry.1m', $settings->retryQueue(retry: 2));
        self::assertSame('notification.travel_request.retry.5m', $settings->retryQueue(retry: 3));
        self::assertSame('notification.travel_request.retry.1500ms', $settings->retryQueue(retry: 4));
        self::assertSame(60_000, $settings->retryDelay(retry: 2));
        self::assertSame('notification.travel_request.dlq', $settings->deadLetterQueue());
    }

    /**
     * @param non-empty-list<positive-int> $retryDelaysMilliseconds
     */
    private function settings(array $retryDelaysMilliseconds): DecisionConsumerSettings
    {
        return new DecisionConsumerSettings(
            exchange: 'travel_request.events',
            queue: 'notification.travel_request',
            bindingKey: 'travel_request.#',
            prefetch: 10,
            retryDelaysMilliseconds: $retryDelaysMilliseconds,
            confirmTimeoutSeconds: 5,
            waitSeconds: 1.0,
        );
    }
}
