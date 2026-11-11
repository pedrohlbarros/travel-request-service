<?php

declare(strict_types=1);

namespace Tests\Infrastructure\TravelRequest\Messaging;

use Application\Shared\Port\DomainEventPublisher;
use Application\TravelRequest\Input\ApproveTravelRequestInput;
use Application\TravelRequest\Input\CancelTravelRequestInput;
use Application\TravelRequest\UseCase\ApproveTravelRequestUseCase;
use Application\TravelRequest\UseCase\CancelTravelRequestUseCase;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\Repository\TravelRequestRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\LaravelTestCase;
use Tests\Support\Shared\Messaging\PublisherFailingAfterTheWrite;
use Tests\Support\Shared\Messaging\UsesTheOutbox;
use Tests\Support\TravelRequest\Fixture\StoresFixtureUsers;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother as Mother;

/**
 * The decision and its event are written in one transaction: both are kept, or neither is.
 */
final class DecisionEventsTest extends LaravelTestCase
{
    use RefreshDatabase;
    use StoresFixtureUsers;
    use UsesTheOutbox;

    /** CloudEvents 1.0 timestamps are RFC 3339. */
    private const string RFC3339_UTC = '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00\z/';

    private const string UUID7 = '/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/';

    protected function setUp(): void
    {
        parent::setUp();
        $this->storeFixtureUsers();
        $this->app->make(TravelRequestRepository::class)
            ->add(travelRequest: Mother::requested());
    }

    public function test_AnApprovalIsStoredWithItsEventAsAValidCloudEvent(): void
    {
        $this->approve();

        $events = $this->storedEvents();
        $event = $events[0];

        self::assertCount(1, $events);
        self::assertSame(TravelRequestStatus::Approved, $this->storedTravelRequest()->status);
        self::assertSame('1.0', $event['specversion']);
        self::assertMatchesRegularExpression(self::UUID7, $event['id']);
        self::assertSame('/travel-request-service', $event['source']);
        self::assertSame('travel_request.approved.v1', $event['type']);
        self::assertSame(Mother::TRAVEL_REQUEST_ID, $event['subject']);
        self::assertMatchesRegularExpression(self::RFC3339_UTC, $event['time']);
        self::assertSame('application/json', $event['datacontenttype']);
        self::assertSame(Mother::TRAVEL_REQUEST_ID, $event['data']['travel_request_id']);
        self::assertSame(Mother::ADMIN_ID, $event['data']['approved_by']);
        self::assertSame(2, $event['data']['version']);
    }

    public function test_ACancellationIsStoredWithItsEvent(): void
    {
        $this->app->make(CancelTravelRequestUseCase::class)
            ->execute(input: new CancelTravelRequestInput(
                travelRequestId: Mother::TRAVEL_REQUEST_ID,
                cancellerId: Mother::ADMIN_ID,
                expectedVersion: TravelRequest::FIRST_VERSION,
            ));

        $events = $this->storedEvents();

        self::assertCount(1, $events);
        self::assertSame('travel_request.cancelled.v1', $events[0]['type']);
        self::assertSame(TravelRequestStatus::Cancelled, $this->storedTravelRequest()->status);
    }

    public function test_WhenTheEventCannotBeStoredTheDecisionIsUndone(): void
    {
        $outbox = $this->app->make(DomainEventPublisher::class);
        $failingAfterTheWrite = new PublisherFailingAfterTheWrite(outbox: $outbox);
        $this->app->instance(DomainEventPublisher::class, $failingAfterTheWrite);

        try {
            $this->approve();
            $hasFailed = false;
        } catch (RuntimeException) {
            $hasFailed = true;
        }

        self::assertTrue($hasFailed);
        self::assertSame([], $this->storedEvents());
        self::assertSame(TravelRequestStatus::Requested, $this->storedTravelRequest()->status);
    }

    private function approve(): void
    {
        $this->app->make(ApproveTravelRequestUseCase::class)
            ->execute(input: new ApproveTravelRequestInput(
                travelRequestId: Mother::TRAVEL_REQUEST_ID,
                approverId: Mother::ADMIN_ID,
                expectedVersion: TravelRequest::FIRST_VERSION,
            ));
    }

    private function storedTravelRequest(): TravelRequest
    {
        return $this->app->make(TravelRequestRepository::class)
            ->get(id: Mother::travelRequestId());
    }

    /**
     * @return list<array{specversion: string, id: string, source: string, type: string, subject: string, time: string, datacontenttype: string, data: array<string, mixed>}>
     */
    private function storedEvents(): array
    {
        $events = [];

        foreach ($this->outboxColumn(column: 'payload') as $payload) {
            $events[] = json_decode($payload, true);
        }

        /** @var list<array{specversion: string, id: string, source: string, type: string, subject: string, time: string, datacontenttype: string, data: array<string, mixed>}> $events */
        return $events;
    }
}
