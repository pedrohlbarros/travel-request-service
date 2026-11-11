<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\TravelRequest\Controller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\LaravelTestCase;
use Tests\Support\TravelRequest\CallsTravelRequestApi;

final class CreateTravelRequestControllerTest extends LaravelTestCase
{
    use CallsTravelRequestApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInEveryone();
    }

    public function test_CreatesARequestedTravelRequestForTheCaller(): void
    {
        $trip = [
            'destination' => '  Lisbon ',
            'departure_date' => self::daysFromToday(days: 10),
            'return_date' => self::daysFromToday(days: 15),
        ];

        $response = $this->postJson(self::TRAVEL_REQUESTS, $trip, $this->bearer(accessToken: $this->requesterToken));

        $id = $response->json('data.id');
        \assert(\is_string($id));

        $response->assertCreated()
            ->assertHeader('ETag', '"1"')
            ->assertHeader('Location', url(self::TRAVEL_REQUESTS . '/' . $id))
            ->assertJsonPath('data.requester', ['id' => $this->requesterId, 'name' => self::REQUESTER_NAME])
            ->assertJsonPath('data.destination', 'Lisbon')
            ->assertJsonPath('data.departure_date', self::daysFromToday(days: 10))
            ->assertJsonPath('data.return_date', self::daysFromToday(days: 15))
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.decided_by', null);
    }

    public function test_TheBodyCannotChooseStatusOrRequester(): void
    {
        $created = $this->createTravelRequestAs(
            token: $this->requesterToken,
            details: ['status' => 'approved', 'requester_id' => $this->adminId],
        );

        self::assertSame('requested', $created['status']);
        self::assertSame(['id' => $this->requesterId, 'name' => self::REQUESTER_NAME], $created['requester']);
    }

    public function test_DepartingTodayIsAllowed(): void
    {
        $created = $this->createTravelRequestAs(
            token: $this->requesterToken,
            details: ['departure_date' => self::daysFromToday(days: 0), 'return_date' => self::daysFromToday(days: 0)],
        );

        self::assertSame(self::daysFromToday(days: 0), $created['departure_date']);
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidTrips(): array
    {
        $departure = self::daysFromToday(days: 10);

        return [
            'empty' => [[], ['destination', 'departure_date', 'return_date']],
            'destinationTooShort' => [['destination' => 'L', 'departure_date' => $departure, 'return_date' => $departure], ['destination']],
            'destinationTooLong' => [
                ['destination' => str_repeat('a', 121), 'departure_date' => $departure, 'return_date' => $departure],
                ['destination'],
            ],
            'notCalendarDates' => [
                ['destination' => 'Lisbon', 'departure_date' => '10/12/2026', 'return_date' => '2026-12-10T00:00:00Z'],
                ['departure_date', 'return_date'],
            ],
            'departureInThePast' => [
                ['destination' => 'Lisbon', 'departure_date' => self::daysFromToday(days: -1), 'return_date' => $departure],
                ['departure_date'],
            ],
            'returnBeforeDeparture' => [
                ['destination' => 'Lisbon', 'departure_date' => $departure, 'return_date' => self::daysFromToday(days: 9)],
                ['return_date'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $trip
     * @param list<string> $invalidFields
     */
    #[DataProvider('invalidTrips')]
    public function test_RejectsAnInvalidTrip(array $trip, array $invalidFields): void
    {
        $response = $this->postJson(self::TRAVEL_REQUESTS, $trip, $this->bearer(accessToken: $this->requesterToken));

        $response->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', '/problems/validation-failed')
            ->assertOnlyJsonValidationErrors($invalidFields);
    }

    public function test_RequiresAuthentication(): void
    {
        $response = $this->postJson(self::TRAVEL_REQUESTS, []);

        $response->assertUnauthorized()
            ->assertJsonPath('type', '/problems/unauthenticated');
    }
}
