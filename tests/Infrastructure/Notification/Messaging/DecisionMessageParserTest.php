<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Notification\Messaging;

use DateTimeImmutable;
use Infrastructure\Notification\Messaging\DecisionMessageParser;
use Infrastructure\Notification\Messaging\UnreadableDecisionMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Notification\Fixture\DecisionMessages;
use Tests\Support\Notification\Fixture\DecisionMother as Mother;

final class DecisionMessageParserTest extends TestCase
{
    public function test_ReadsAnApproval(): void
    {
        $body = DecisionMessages::body(event: DecisionMessages::event());

        $input = new DecisionMessageParser()
            ->parse(body: $body);

        self::assertEquals(Mother::input(), $input);
        self::assertEquals(new DateTimeImmutable(Mother::DECIDED_AT), $input->decidedAt);
    }

    public function test_ReadsACancellationAndWhoCancelled(): void
    {
        $body = DecisionMessages::body(event: DecisionMessages::event(type: DecisionMessages::CANCELLED));

        $input = new DecisionMessageParser()
            ->parse(body: $body);

        self::assertSame('cancelled', $input->outcome);
        self::assertSame(Mother::ADMIN_ID, $input->decidedBy);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unreadableBodies(): array
    {
        return [
            'notJson' => ['{not json', 'the body is not JSON'],
            'notAnObject' => ['"text"', 'the body is not a JSON object'],
            'otherType' => [
                DecisionMessages::body(event: DecisionMessages::event(type: 'travel_request.requested.v1')),
                '"travel_request.requested.v1" is not a decision type',
            ],
            'noType' => [DecisionMessages::body(event: DecisionMessages::event(overrides: ['type' => null])), '"type" is missing'],
            'noData' => [DecisionMessages::body(event: DecisionMessages::event(overrides: ['data' => 'none'])), '"data" is missing'],
            'noRequester' => [
                DecisionMessages::body(event: DecisionMessages::event(dataOverrides: ['requester' => null])),
                '"requester" is missing',
            ],
            'noDecider' => [
                DecisionMessages::body(event: DecisionMessages::event(dataOverrides: ['approved_by' => null])),
                '"approved_by" is missing',
            ],
            'badDate' => [
                DecisionMessages::body(event: DecisionMessages::event(dataOverrides: ['departure_date' => '01/12/2026'])),
                '"departure_date" is not a date',
            ],
            'badTime' => [
                DecisionMessages::body(event: DecisionMessages::event(overrides: ['time' => 'yesterday'])),
                '"time" is not a date',
            ],
        ];
    }

    #[DataProvider('unreadableBodies')]
    public function test_RejectsWhatIsNotADecision(string $body, string $reason): void
    {
        $this->expectException(UnreadableDecisionMessage::class);
        $this->expectExceptionMessageIs("The decision message cannot be read: {$reason}.");

        new DecisionMessageParser()
            ->parse(body: $body);
    }
}
