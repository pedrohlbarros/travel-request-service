<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\TravelRequest\Controller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\LaravelTestCase;
use Tests\Support\TravelRequest\CallsTravelRequestApi;

final class ChangeTravelRequestDetailsControllerTest extends LaravelTestCase
{
    use CallsTravelRequestApi;
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInEveryone();
        $created = $this->createTravelRequestAs(token: $this->requesterToken);
        $this->path = self::TRAVEL_REQUESTS . '/' . $this->idOf(travelRequest: $created);
    }

    public function test_ChangesOnlyTheFieldsSent(): void
    {
        $response = $this->patchAs(token: $this->requesterToken, ifMatch: '"1"', body: ['destination' => 'Tokyo']);

        $response->assertOk()
            ->assertHeader('ETag', '"2"')
            ->assertJsonPath('data.destination', 'Tokyo')
            ->assertJsonPath('data.departure_date', self::daysFromToday(days: 10))
            ->assertJsonPath('data.return_date', self::daysFromToday(days: 15))
            ->assertJsonPath('data.version', 2);
    }

    public function test_ChangesBothDates(): void
    {
        $newDates = ['departure_date' => self::daysFromToday(days: 20), 'return_date' => self::daysFromToday(days: 22)];

        $response = $this->patchAs(token: $this->requesterToken, ifMatch: '"1"', body: $newDates);

        $response->assertOk()
            ->assertJsonPath('data.departure_date', self::daysFromToday(days: 20))
            ->assertJsonPath('data.return_date', self::daysFromToday(days: 22));
    }

    public function test_TheSameDetailsKeepTheVersion(): void
    {
        $response = $this->patchAs(token: $this->requesterToken, ifMatch: '"1"', body: ['destination' => 'Lisbon']);

        $response->assertOk()
            ->assertHeader('ETag', '"1"')
            ->assertJsonPath('data.version', 1);
    }

    public function test_TheNextReadSeesTheChange(): void
    {
        $this->getJson($this->path, $this->bearer(accessToken: $this->requesterToken));
        $this->patchAs(token: $this->requesterToken, ifMatch: '"1"', body: ['destination' => 'Tokyo']);

        $response = $this->getJson($this->path, $this->bearer(accessToken: $this->requesterToken));

        $response->assertOk()
            ->assertHeader('ETag', '"2"')
            ->assertJsonPath('data.destination', 'Tokyo');
    }

    public function test_RequiresIfMatch(): void
    {
        $headers = $this->bearer(accessToken: $this->requesterToken);

        $response = $this->patchJson($this->path, ['destination' => 'Tokyo'], $headers);

        $response->assertStatus(428)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', '/problems/precondition-required');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonMatchingIfMatches(): array
    {
        return [
            'staleVersion' => ['"2"'],
            'weakTag' => ['W/"1"'],
            'anyTag' => ['*'],
            'list' => ['"1", "2"'],
            'unquoted' => ['1'],
            'empty' => [''],
        ];
    }

    #[DataProvider('nonMatchingIfMatches')]
    public function test_AnIfMatchOtherThanTheCurrentStrongTagFails(string $ifMatch): void
    {
        $response = $this->patchAs(token: $this->requesterToken, ifMatch: $ifMatch, body: ['destination' => 'Tokyo']);

        $response->assertStatus(412)
            ->assertJsonPath('type', '/problems/version-mismatch');
    }

    public function test_SomeoneElsesRequestIsNotFoundEvenWithAWrongVersion(): void
    {
        $response = $this->patchAs(token: $this->otherUserToken, ifMatch: '"7"', body: ['destination' => 'Tokyo']);

        $response->assertNotFound()
            ->assertJsonPath('type', '/problems/not-found');
    }

    public function test_AdminCannotEditSomeoneElsesRequest(): void
    {
        $response = $this->patchAs(token: $this->adminToken, ifMatch: '"1"', body: ['destination' => 'Tokyo']);

        $response->assertNotFound();
    }

    public function test_ADecidedRequestIsNotEditable(): void
    {
        $this->writeAs(token: $this->adminToken, path: $this->path . '/approve', ifMatch: '"1"');

        $response = $this->patchAs(token: $this->requesterToken, ifMatch: '"2"', body: ['destination' => 'Tokyo']);

        $response->assertConflict()
            ->assertJsonPath('type', '/problems/travel-request-not-editable');
    }

    /**
     * @return array<string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidChanges(): array
    {
        return [
            'nothingSent' => [[], ['destination', 'departure_date', 'return_date']],
            'destinationNullCountsAsNotSent' => [['destination' => null], ['destination', 'departure_date', 'return_date']],
            'destinationTooShort' => [['destination' => 'L'], ['destination']],
            'notACalendarDate' => [['departure_date' => '2026/12/01'], ['departure_date']],
            'returnBeforeDepartureSent' => [
                ['departure_date' => self::daysFromToday(days: 20), 'return_date' => self::daysFromToday(days: 19)],
                ['return_date'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string> $invalidFields
     */
    #[DataProvider('invalidChanges')]
    public function test_RejectsAnInvalidChange(array $body, array $invalidFields): void
    {
        $response = $this->patchAs(token: $this->requesterToken, ifMatch: '"1"', body: $body);

        $response->assertUnprocessable()
            ->assertJsonPath('type', '/problems/validation-failed')
            ->assertOnlyJsonValidationErrors($invalidFields);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function periodsTheDomainRejects(): array
    {
        return [
            'returnBeforeStoredDeparture' => [['return_date' => self::daysFromToday(days: 9)]],
            'departureAfterStoredReturn' => [['departure_date' => self::daysFromToday(days: 16)]],
            'departureInThePast' => [['departure_date' => self::daysFromToday(days: -1)]],
        ];
    }

    /**
     * @param array<string, string> $body
     */
    #[DataProvider('periodsTheDomainRejects')]
    public function test_RejectsAPeriodThatBreaksTheRules(array $body): void
    {
        $response = $this->patchAs(token: $this->requesterToken, ifMatch: '"1"', body: $body);

        $response->assertUnprocessable()
            ->assertJsonPath('type', '/problems/validation-failed');
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return TestResponse<Response>
     */
    private function patchAs(string $token, string $ifMatch, array $body): TestResponse
    {
        return $this->writeAs(
            token: $token,
            path: $this->path,
            ifMatch: $ifMatch,
            body: $body,
            method: 'PATCH',
        );
    }
}
