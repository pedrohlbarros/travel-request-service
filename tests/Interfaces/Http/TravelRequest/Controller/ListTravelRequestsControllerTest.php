<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\TravelRequest\Controller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\LaravelTestCase;
use Tests\Support\TravelRequest\CallsTravelRequestApi;

final class ListTravelRequestsControllerTest extends LaravelTestCase
{
    use CallsTravelRequestApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInEveryone();
    }

    public function test_AUserListsOnlyOwnRequestsNewestFirst(): void
    {
        $older = $this->createTravelRequestAs(token: $this->requesterToken, details: ['destination' => 'Lisbon']);
        $this->createTravelRequestAs(token: $this->otherUserToken);
        $newer = $this->createTravelRequestAs(token: $this->requesterToken, details: ['destination' => 'Tokyo']);

        $response = $this->listAs(token: $this->requesterToken);

        $response->assertOk()
            ->assertExactJson([
                'data' => [$newer, $older],
                'meta' => ['per_page' => 20, 'next_cursor' => null, 'prev_cursor' => null],
                'links' => ['next' => null, 'prev' => null],
            ]);
    }

    public function test_AnAdminListsEveryonesRequests(): void
    {
        $this->createTravelRequestAs(token: $this->requesterToken);
        $this->createTravelRequestAs(token: $this->otherUserToken);

        $response = $this->listAs(token: $this->adminToken);

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_FiltersCombine(): void
    {
        $lisbon = $this->createTravelRequestAs(token: $this->requesterToken, details: ['destination' => 'Lisbon']);
        $tokyo = $this->createTravelRequestAs(token: $this->requesterToken, details: ['destination' => 'Tokyo']);
        $this->createTravelRequestAs(
            token: $this->requesterToken,
            details: ['destination' => 'Lisbon', 'departure_date' => self::daysFromToday(days: 40), 'return_date' => self::daysFromToday(days: 41)],
        );
        $this->writeAs(
            token: $this->adminToken,
            path: self::TRAVEL_REQUESTS . '/' . $this->idOf(travelRequest: $tokyo) . '/approve',
            ifMatch: '"1"',
        );

        $response = $this->listAs(
            token: $this->requesterToken,
            query: [
                'status' => 'requested,cancelled',
                'destination' => 'lis',
                'created_from' => self::daysFromToday(days: 0),
                'created_to' => self::daysFromToday(days: 0),
                'travel_from' => self::daysFromToday(days: 12),
                'travel_to' => self::daysFromToday(days: 30),
            ],
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->idOf(travelRequest: $lisbon));
    }

    public function test_FiltersByStatus(): void
    {
        $approved = $this->createTravelRequestAs(token: $this->requesterToken);
        $this->createTravelRequestAs(token: $this->requesterToken);
        $this->writeAs(
            token: $this->adminToken,
            path: self::TRAVEL_REQUESTS . '/' . $this->idOf(travelRequest: $approved) . '/approve',
            ifMatch: '"1"',
        );

        $response = $this->listAs(token: $this->requesterToken, query: ['status' => 'approved']);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'approved');
    }

    public function test_PagesWithCursorLinks(): void
    {
        $first = $this->createTravelRequestAs(token: $this->requesterToken);
        $second = $this->createTravelRequestAs(token: $this->requesterToken);
        $third = $this->createTravelRequestAs(token: $this->requesterToken);

        $firstPage = $this->listAs(token: $this->requesterToken, query: ['per_page' => 2]);
        $nextLink = $firstPage->json('links.next');
        \assert(\is_string($nextLink));
        $secondPage = $this->getJson($nextLink, $this->bearer(accessToken: $this->requesterToken));
        $previousLink = $secondPage->json('links.prev');
        \assert(\is_string($previousLink));
        $backToFirst = $this->getJson($previousLink, $this->bearer(accessToken: $this->requesterToken));

        $firstPage->assertJsonPath('data.0.id', $this->idOf(travelRequest: $third))
            ->assertJsonPath('data.1.id', $this->idOf(travelRequest: $second))
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.prev_cursor', null);
        self::assertStringContainsString('per_page=2', $nextLink);
        $secondPage->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->idOf(travelRequest: $first))
            ->assertJsonPath('links.next', null);
        $backToFirst->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $this->idOf(travelRequest: $third));
    }

    public function test_ANewRequestShowsUpInAListAlreadyRead(): void
    {
        $this->listAs(token: $this->requesterToken);
        $this->listAs(token: $this->adminToken);
        $this->createTravelRequestAs(token: $this->requesterToken);

        $ownList = $this->listAs(token: $this->requesterToken);
        $adminList = $this->listAs(token: $this->adminToken);

        $ownList->assertJsonCount(1, 'data');
        $adminList->assertJsonCount(1, 'data');
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidQueries(): array
    {
        return [
            'unknownStatus' => [['status' => 'requested,pending'], ['status']],
            'emptyStatus' => [['status' => ''], ['status']],
            'destinationTooLong' => [['destination' => str_repeat('a', 121)], ['destination']],
            'notCalendarDates' => [
                ['created_from' => 'yesterday', 'created_to' => '2026-13-01', 'travel_from' => '1/1/2027', 'travel_to' => '2027'],
                ['created_from', 'created_to', 'travel_from', 'travel_to'],
            ],
            'createdRangeBackwards' => [['created_from' => '2026-11-10', 'created_to' => '2026-11-09'], ['created_to']],
            'travelRangeBackwards' => [['travel_from' => '2026-12-10', 'travel_to' => '2026-12-09'], ['travel_to']],
            'perPageZero' => [['per_page' => 0], ['per_page']],
            'perPageOverTheMaximum' => [['per_page' => 101], ['per_page']],
            'perPageNotANumber' => [['per_page' => 'many'], ['per_page']],
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @param list<string> $invalidFields
     */
    #[DataProvider('invalidQueries')]
    public function test_RejectsAnInvalidQuery(array $query, array $invalidFields): void
    {
        $response = $this->listAs(token: $this->requesterToken, query: $query);

        $response->assertUnprocessable()
            ->assertJsonPath('type', '/problems/validation-failed')
            ->assertOnlyJsonValidationErrors($invalidFields);
    }

    public function test_TheLargestPageIsAllowed(): void
    {
        $response = $this->listAs(token: $this->requesterToken, query: ['per_page' => 100]);

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_RejectsACursorItDidNotIssue(): void
    {
        $response = $this->listAs(token: $this->requesterToken, query: ['cursor' => 'forged']);

        $response->assertUnprocessable()
            ->assertJsonPath('type', '/problems/validation-failed')
            ->assertJsonPath('detail', 'The cursor is not one this list returned.');
    }

    public function test_RequiresAuthentication(): void
    {
        $response = $this->getJson(self::TRAVEL_REQUESTS);

        $response->assertUnauthorized();
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return TestResponse<Response>
     */
    private function listAs(string $token, array $query = []): TestResponse
    {
        $queryString = $query === [] ? '' : '?' . http_build_query($query);

        return $this->getJson(self::TRAVEL_REQUESTS . $queryString, $this->bearer(accessToken: $token));
    }
}
