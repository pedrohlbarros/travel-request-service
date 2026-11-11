<?php

declare(strict_types=1);

namespace Tests\Application\Notification\UseCase;

use Application\Notification\Output\NotificationDelivery;
use Application\Notification\UseCase\NotifyRequesterOfDecisionUseCase;
use Domain\Notification\Enum\DecisionOutcome;
use Domain\Notification\Exception\InvalidDecision;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Notification\Fixture\DecisionMother as Mother;
use Tests\Support\Notification\Port\InMemoryProcessedEvents;
use Tests\Support\Notification\Port\SpyDecisionBroadcaster;
use Tests\Support\Notification\Port\SpyDecisionNotifier;
use Tests\Support\Shared\Port\ImmediateTransaction;

final class NotifyRequesterOfDecisionUseCaseTest extends TestCase
{
    private InMemoryProcessedEvents $processedEvents;

    private SpyDecisionBroadcaster $broadcaster;

    private ImmediateTransaction $transaction;

    protected function setUp(): void
    {
        $this->processedEvents = new InMemoryProcessedEvents();
        $this->broadcaster = new SpyDecisionBroadcaster();
        $this->transaction = new ImmediateTransaction();
    }

    public function test_NotifiesTheRequesterOnTheDurableChannelsAndThenBroadcasts(): void
    {
        $notifier = new SpyDecisionNotifier();

        $delivery = $this->useCase(notifier: $notifier)
            ->execute(input: Mother::input());

        $notified = $notifier->notifiedDecisions[0] ?? null;

        self::assertSame(NotificationDelivery::Delivered, $delivery);
        self::assertSame([Mother::EVENT_ID], $this->processedEvents->processedEventIds);
        self::assertSame(1, $this->transaction->completedRuns);
        self::assertCount(1, $notifier->notifiedDecisions);
        self::assertNotNull($notified);
        self::assertSame(Mother::EVENT_ID, $notified->eventId->value);
        self::assertSame(Mother::REQUESTER_EMAIL, $notified->recipient->email);
        self::assertSame(DecisionOutcome::Approved, $notified->outcome);
        self::assertEquals([$notified], $this->broadcaster->broadcastDecisions);
    }

    public function test_ARedeliveredEventNotifiesNobodyAgain(): void
    {
        $notifier = new SpyDecisionNotifier();
        $useCase = $this->useCase(notifier: $notifier);
        $useCase->execute(input: Mother::input());

        $redelivery = $useCase->execute(input: Mother::input());

        self::assertSame(NotificationDelivery::AlreadyDelivered, $redelivery);
        self::assertCount(1, $notifier->notifiedDecisions);
        self::assertCount(1, $this->broadcaster->broadcastDecisions);
    }

    public function test_AnotherEventForTheSameRequestIsNotifiedToo(): void
    {
        $notifier = new SpyDecisionNotifier();
        $useCase = $this->useCase(notifier: $notifier);
        $useCase->execute(input: Mother::input());

        $useCase->execute(input: Mother::input(outcome: 'cancelled', eventId: Mother::OTHER_EVENT_ID));

        self::assertCount(2, $notifier->notifiedDecisions);
        self::assertCount(2, $this->broadcaster->broadcastDecisions);
    }

    public function test_NothingIsBroadcastWhenTheDurableChannelsFail(): void
    {
        $failingNotifier = new SpyDecisionNotifier(isFailing: true);

        try {
            $this->useCase(notifier: $failingNotifier)
                ->execute(input: Mother::input());
            self::fail('The delivery must fail.');
        } catch (RuntimeException $failure) {
            self::assertSame(SpyDecisionNotifier::FAILURE, $failure->getMessage());
        }

        self::assertSame([], $this->broadcaster->broadcastDecisions);
    }

    public function test_AnInvalidDecisionIsRejectedBeforeAnythingIsRecorded(): void
    {
        $notifier = new SpyDecisionNotifier();

        try {
            $this->useCase(notifier: $notifier)
                ->execute(input: Mother::input(requesterEmail: 'not-an-email'));
            self::fail('An invalid decision must be rejected.');
        } catch (InvalidDecision) {
        }

        self::assertSame([], $this->processedEvents->processedEventIds);
        self::assertSame([], $notifier->notifiedDecisions);
    }

    private function useCase(SpyDecisionNotifier $notifier): NotifyRequesterOfDecisionUseCase
    {
        return new NotifyRequesterOfDecisionUseCase(
            processedEvents: $this->processedEvents,
            notifier: $notifier,
            broadcaster: $this->broadcaster,
            transaction: $this->transaction,
        );
    }
}
