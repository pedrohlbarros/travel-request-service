<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Console;

use Illuminate\Contracts\Console\Kernel;
use Infrastructure\Shared\Console\TapMessagesCommand;
use Infrastructure\Shared\Messaging\RabbitMqConnectionFactory;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Tests\Support\LaravelTestCase;

final class TapMessagesCommandTest extends LaravelTestCase
{
    private const string TRAVEL_REQUEST_EXCHANGE = 'travel_request.events.test';

    private const string OTHER_EXCHANGE = 'shared.events.tap-test';

    private const string ROUTING_KEY = 'travel_request.approved.v1';

    private const string TRACEPARENT = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

    protected function tearDown(): void
    {
        $connection = $this->app->make(RabbitMqConnectionFactory::class)
            ->create();
        $channel = $connection->channel();
        $channel->queue_delete(TapMessagesCommand::QUEUE_PREFIX . self::TRAVEL_REQUEST_EXCHANGE);
        $channel->queue_delete(TapMessagesCommand::QUEUE_PREFIX . self::OTHER_EXCHANGE);
        $connection->close();
        parent::tearDown();
    }

    public function test_TheFirstTapCreatesTheQueueAndTheNextOnePrintsWhatArrived(): void
    {
        $this->command(command: 'messaging:tap')
            ->expectsOutputToContain('No messages in inspection.travel_request.events.test yet')
            ->assertSuccessful();
        $this->publishTo(exchange: self::TRAVEL_REQUEST_EXCHANGE, body: '{"type":"travel_request.approved.v1"}');


        $printed = $this->tapOutput();
        $printedAgain = $this->tapOutput();

        self::assertStringContainsString('"routing_key": "travel_request.approved.v1"', $printed);
        self::assertStringContainsString('"traceparent": "' . self::TRACEPARENT . '"', $printed);
        self::assertStringContainsString('"content_type": "application/cloudevents+json"', $printed);
        self::assertStringContainsString('"payload": {', $printed);
        self::assertStringContainsString('No messages', $printedAgain);
    }

    public function test_TapsAnyExchangeAndPrintsAtMostTheLimit(): void
    {
        $this->command(command: 'messaging:tap', parameters: ['exchange' => self::OTHER_EXCHANGE])
            ->assertSuccessful();
        $this->publishTo(exchange: self::OTHER_EXCHANGE, body: 'first, not JSON');
        $this->publishTo(exchange: self::OTHER_EXCHANGE, body: 'second, not JSON');

        $this->command(command: 'messaging:tap', parameters: ['exchange' => self::OTHER_EXCHANGE, '--limit' => 1])
            ->expectsOutputToContain('"payload": "first, not JSON"')
            ->doesntExpectOutputToContain('second')
            ->assertSuccessful();
    }

    private function tapOutput(): string
    {
        $console = $this->app->make(Kernel::class);
        $console->call('messaging:tap');

        return $console->output();
    }

    private function publishTo(string $exchange, string $body): void
    {
        $connection = $this->app->make(RabbitMqConnectionFactory::class)
            ->create();
        $channel = $connection->channel();
        $message = new AMQPMessage(
            $body,
            [
                'content_type' => 'application/cloudevents+json',
                'type' => self::ROUTING_KEY,
                'application_headers' => new AMQPTable(['traceparent' => self::TRACEPARENT]),
            ],
        );
        $channel->basic_publish($message, $exchange, self::ROUTING_KEY);
        $connection->close();
    }
}
