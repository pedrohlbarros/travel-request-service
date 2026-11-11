<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Notification\Console;

use Illuminate\Contracts\Console\Kernel;
use Infrastructure\Notification\Messaging\DecisionMessageHandler;
use Tests\Support\LaravelTestCase;
use Tests\Support\Notification\Fixture\DecisionMessages;
use Tests\Support\Notification\Fixture\UsesDecisionQueues;

final class DeadLetterCommandTest extends LaravelTestCase
{
    use UsesDecisionQueues;

    private const array DEAD_LETTER_HEADERS = [
        DecisionMessageHandler::DEATH_REASON_HEADER => DecisionMessageHandler::RETRIES_EXHAUSTED,
        DecisionMessageHandler::LAST_ERROR_HEADER => 'The mail server is down.',
        DecisionMessageHandler::RETRY_COUNT_HEADER => 3,
        'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->declareDecisionQueues();
        $body = DecisionMessages::body(event: DecisionMessages::event());
        $this->parkInDeadLetterQueue(body: $body, headers: self::DEAD_LETTER_HEADERS);
    }

    protected function tearDown(): void
    {
        $this->closeDecisionQueues();
        parent::tearDown();
    }

    public function test_ListsTheDeadLettersWithTheirReasonAndKeepsThem(): void
    {
        $output = $this->dlqOutput(parameters: []);

        $stillDeadLettered = $this->deadLetters();

        self::assertStringContainsString('"reason": "retries_exhausted"', $output);
        self::assertStringContainsString('"error": "The mail server is down."', $output);
        self::assertStringContainsString('"retries": 3', $output);
        self::assertStringContainsString('"type": "travel_request.approved.v1"', $output);
        self::assertStringContainsString('Listed 1 dead-lettered messages.', $output);
        self::assertCount(1, $stillDeadLettered);
    }

    public function test_ReplaySendsThemBackForAFreshSetOfRetries(): void
    {
        $output = $this->dlqOutput(parameters: ['--replay' => true]);

        $mainQueue = $this->decisionSettings()->queue;
        $replayed = $this->messagesIn(queue: $mainQueue);
        $headers = self::headersOf(message: $replayed[0]);

        self::assertStringContainsString('Replayed 1 dead-lettered messages.', $output);
        self::assertSame([], $this->deadLetters());
        self::assertCount(1, $replayed);
        self::assertSame(
            ['traceparent' => self::DEAD_LETTER_HEADERS['traceparent'], DecisionMessageHandler::RETRY_COUNT_HEADER => 0],
            $headers,
        );
    }

    public function test_TheLimitBoundsHowManyAreTaken(): void
    {
        $output = $this->dlqOutput(parameters: ['--limit' => 0]);

        self::assertStringContainsString('Listed 0 dead-lettered messages.', $output);
        self::assertCount(1, $this->deadLetters());
    }

    /**
     * @param array<string, bool|int> $parameters
     */
    private function dlqOutput(array $parameters): string
    {
        $console = $this->app->make(Kernel::class);
        $console->call('notification:dlq', $parameters);

        return $console->output();
    }
}
