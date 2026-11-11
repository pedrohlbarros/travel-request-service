<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Notification\Messaging;

use Infrastructure\Notification\Messaging\DecisionMessageHandler;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\TestCase;

final class DecisionMessageHeadersTest extends TestCase
{
    public function test_AMessageWithoutHeadersIsOnItsFirstTry(): void
    {
        $message = new AMQPMessage('{}');

        $headers = DecisionMessageHandler::headersOf(message: $message);
        $retryCount = DecisionMessageHandler::retryCountOf(message: $message);

        self::assertSame([], $headers);
        self::assertSame(0, $retryCount);
    }

    public function test_ARetryCountThatIsNotANumberCountsAsNone(): void
    {
        $headers = new AMQPTable([DecisionMessageHandler::RETRY_COUNT_HEADER => 'two']);
        $message = new AMQPMessage('{}', ['application_headers' => $headers]);

        $retryCount = DecisionMessageHandler::retryCountOf(message: $message);

        self::assertSame(0, $retryCount);
    }
}
