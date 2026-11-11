<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\TravelRequest\Controller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\LaravelTestCase;
use Tests\Support\TravelRequest\CallsTravelRequestApi;

/**
 * Approve and cancel share their rules: admin only, If-Match required, only from requested.
 */
final class DecideTravelRequestControllersTest extends LaravelTestCase
{
    use CallsTravelRequestApi;
    use RefreshDatabase;

    private const string APPROVE = '/approve';

    private const string CANCEL = '/cancel';

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInEveryone();
        $created = $this->createTravelRequestAs(token: $this->requesterToken);
        $this->path = self::TRAVEL_REQUESTS . '/' . $this->idOf(travelRequest: $created);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function decisions(): array
    {
        return [
            'approve' => [self::APPROVE, 'approved'],
            'cancel' => [self::CANCEL, 'cancelled'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function actions(): array
    {
        return [
            'approve' => [self::APPROVE],
            'cancel' => [self::CANCEL],
        ];
    }

    #[DataProvider('decisions')]
    public function test_AdminDecidesARequestedTravelRequest(string $action, string $decidedStatus): void
    {
        $response = $this->writeAs(token: $this->adminToken, path: $this->path . $action, ifMatch: '"1"');

        $response->assertOk()
            ->assertHeader('ETag', '"2"')
            ->assertJsonPath('data.status', $decidedStatus)
            ->assertJsonPath('data.decided_by', ['id' => $this->adminId, 'name' => self::ADMIN_NAME])
            ->assertJsonPath('data.requester.name', self::REQUESTER_NAME)
            ->assertJsonPath('data.version', 2);
    }

    #[DataProvider('decisions')]
    public function test_AdminCanDecideOwnTravelRequest(string $action, string $decidedStatus): void
    {
        $own = $this->createTravelRequestAs(token: $this->adminToken);
        $ownPath = self::TRAVEL_REQUESTS . '/' . $this->idOf(travelRequest: $own);

        $response = $this->writeAs(token: $this->adminToken, path: $ownPath . $action, ifMatch: '"1"');

        $response->assertOk()
            ->assertJsonPath('data.status', $decidedStatus);
    }

    #[DataProvider('actions')]
    public function test_ARegularUserIsForbiddenBeforeAnyOtherCheck(string $action): void
    {
        $withoutIfMatch = $this->postJson($this->path . $action, [], $this->bearer(accessToken: $this->requesterToken));
        $ofNothing = $this->writeAs(
            token: $this->requesterToken,
            path: self::TRAVEL_REQUESTS . '/019a6a10-aaaa-7bbb-8ccc-000000000999' . $action,
            ifMatch: '"1"',
        );

        $withoutIfMatch->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', '/problems/forbidden');
        $ofNothing->assertForbidden();
    }

    #[DataProvider('actions')]
    public function test_RequiresIfMatch(string $action): void
    {
        $response = $this->postJson($this->path . $action, [], $this->bearer(accessToken: $this->adminToken));

        $response->assertStatus(428)
            ->assertJsonPath('type', '/problems/precondition-required');
    }

    #[DataProvider('actions')]
    public function test_AStaleVersionFails(string $action): void
    {
        $response = $this->writeAs(token: $this->adminToken, path: $this->path . $action, ifMatch: '"2"');

        $response->assertStatus(412)
            ->assertJsonPath('type', '/problems/version-mismatch');
    }

    #[DataProvider('actions')]
    public function test_AnUnknownTravelRequestIsNotFoundBeforeTheVersion(string $action): void
    {
        $unknownPath = self::TRAVEL_REQUESTS . '/019a6a10-aaaa-7bbb-8ccc-000000000999' . $action;

        $response = $this->writeAs(token: $this->adminToken, path: $unknownPath, ifMatch: 'W/"oops"');

        $response->assertNotFound();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function decisionsAfterADecision(): array
    {
        return [
            'cancelApproved' => [self::APPROVE, self::CANCEL],
            'approveCancelled' => [self::CANCEL, self::APPROVE],
            'approveTwice' => [self::APPROVE, self::APPROVE],
            'cancelTwice' => [self::CANCEL, self::CANCEL],
        ];
    }

    #[DataProvider('decisionsAfterADecision')]
    public function test_ADecidedTravelRequestCannotBeDecidedAgain(string $firstAction, string $secondAction): void
    {
        $this->writeAs(token: $this->adminToken, path: $this->path . $firstAction, ifMatch: '"1"');

        $response = $this->writeAs(token: $this->adminToken, path: $this->path . $secondAction, ifMatch: '"2"');

        $response->assertConflict()
            ->assertJsonPath('type', '/problems/invalid-status-transition');
    }

    public function test_TheRequesterSeesTheDecision(): void
    {
        $this->getJson($this->path, $this->bearer(accessToken: $this->requesterToken));
        $this->writeAs(token: $this->adminToken, path: $this->path . self::APPROVE, ifMatch: '"1"');

        $response = $this->getJson($this->path, $this->bearer(accessToken: $this->requesterToken));

        $response->assertOk()
            ->assertHeader('ETag', '"2"')
            ->assertJsonPath('data.status', 'approved');
    }
}
