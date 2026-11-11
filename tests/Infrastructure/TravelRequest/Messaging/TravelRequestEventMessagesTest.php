<?php

declare(strict_types=1);

namespace Tests\Infrastructure\TravelRequest\Messaging;

use DateTimeImmutable;
use Domain\TravelRequest\Event\TravelRequestApproved;
use Domain\TravelRequest\Event\TravelRequestCancelled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\TravelRequest\Messaging\TravelRequestEventMessages;
use Tests\Support\LaravelTestCase;
use Tests\Support\Shared\Messaging\SampleEvent;
use Tests\Support\TravelRequest\Fixture\StoresFixtureUsers;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother as Mother;

final class TravelRequestEventMessagesTest extends LaravelTestCase
{
    use RefreshDatabase;
    use StoresFixtureUsers;

    private const string EXCHANGE = 'travel_request.events.test';

    private const string REQUESTER_EMAIL = 'monkeydluffy@example.com';

    private const int DECIDED_VERSION = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storeFixtureUsers();
    }

    public function test_AnApprovalIsAFatEventWithTheRequesterContact(): void
    {
        $approvedAt = Mother::moment(moment: Mother::LATER);
        $approved = new TravelRequestApproved(
            travelRequestId: Mother::travelRequestId(),
            requesterId: Mother::userId(id: Mother::REQUESTER_ID),
            approvedBy: Mother::userId(id: Mother::ADMIN_ID),
            destination: Mother::destination(),
            period: Mother::period(),
            version: self::DECIDED_VERSION,
            occurredAt: $approvedAt,
        );

        $message = $this->messages()
            ->toMessage(event: $approved);

        self::assertSame(self::EXCHANGE, $message->exchange);
        self::assertSame('travel_request.approved.v1', $message->routingKey);
        self::assertSame(Mother::TRAVEL_REQUEST_ID, $message->subject);
        self::assertEquals($approvedAt, $message->occurredAt);
        self::assertSame(
            [
                'travel_request_id' => Mother::TRAVEL_REQUEST_ID,
                'requester' => [
                    'id' => Mother::REQUESTER_ID,
                    'name' => Mother::USER_NAMES[Mother::REQUESTER_ID],
                    'email' => self::REQUESTER_EMAIL,
                ],
                'destination' => Mother::DESTINATION,
                'departure_date' => Mother::DEPARTURE_DATE,
                'return_date' => Mother::RETURN_DATE,
                'status' => 'approved',
                'version' => self::DECIDED_VERSION,
                'approved_by' => Mother::ADMIN_ID,
            ],
            $message->eventData,
        );
    }

    public function test_ACancellationNamesWhoCancelled(): void
    {
        $cancelled = new TravelRequestCancelled(
            travelRequestId: Mother::travelRequestId(),
            requesterId: Mother::userId(id: Mother::REQUESTER_ID),
            cancelledBy: Mother::userId(id: Mother::ADMIN_ID),
            destination: Mother::destination(),
            period: Mother::period(),
            version: self::DECIDED_VERSION,
            occurredAt: Mother::moment(moment: Mother::LATER),
        );

        $message = $this->messages()
            ->toMessage(event: $cancelled);

        self::assertSame('travel_request.cancelled.v1', $message->routingKey);
        self::assertSame('cancelled', $message->eventData['status']);
        self::assertSame(Mother::ADMIN_ID, $message->eventData['cancelled_by']);
        self::assertArrayNotHasKey('approved_by', $message->eventData);
    }

    public function test_MapsOnlyTravelRequestDecisions(): void
    {
        $messages = $this->messages();
        $otherContextEvent = new SampleEvent(subject: 'not a travel request', occurredAt: new DateTimeImmutable());
        $approved = new TravelRequestApproved(
            travelRequestId: Mother::travelRequestId(),
            requesterId: Mother::userId(id: Mother::REQUESTER_ID),
            approvedBy: Mother::userId(id: Mother::ADMIN_ID),
            destination: Mother::destination(),
            period: Mother::period(),
            version: self::DECIDED_VERSION,
            occurredAt: Mother::moment(moment: Mother::LATER),
        );

        self::assertTrue($messages->supports(event: $approved));
        self::assertFalse($messages->supports(event: $otherContextEvent));
    }

    private function messages(): TravelRequestEventMessages
    {
        return new TravelRequestEventMessages(exchange: self::EXCHANGE);
    }
}
