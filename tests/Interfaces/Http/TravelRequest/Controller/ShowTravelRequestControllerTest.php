<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\TravelRequest\Controller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\LaravelTestCase;
use Tests\Support\TravelRequest\CallsTravelRequestApi;

final class ShowTravelRequestControllerTest extends LaravelTestCase
{
    use CallsTravelRequestApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInEveryone();
    }

    public function test_RequesterSeesOwnTravelRequestWithItsVersionAsETag(): void
    {
        $created = $this->createTravelRequestAs(token: $this->requesterToken);

        $path = $this->pathOf(travelRequest: $created);

        $response = $this->getJson($path, $this->bearer(accessToken: $this->requesterToken));

        $response->assertOk()
            ->assertHeader('ETag', '"1"')
            ->assertExactJson(['data' => $created]);
    }

    public function test_AdminSeesAnyTravelRequest(): void
    {
        $created = $this->createTravelRequestAs(token: $this->requesterToken);

        $path = $this->pathOf(travelRequest: $created);

        $response = $this->getJson($path, $this->bearer(accessToken: $this->adminToken));

        $response->assertOk()
            ->assertJsonPath('data.id', $this->idOf(travelRequest: $created));
    }

    public function test_SomeoneElsesTravelRequestIsNotFound(): void
    {
        $created = $this->createTravelRequestAs(token: $this->requesterToken);

        $path = $this->pathOf(travelRequest: $created);

        $response = $this->getJson($path, $this->bearer(accessToken: $this->otherUserToken));

        $response->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', '/problems/not-found')
            ->assertJsonPath('detail', 'The resource was not found.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function idsOfNothing(): array
    {
        return [
            'unknownUuid7' => ['019a6a10-aaaa-7bbb-8ccc-000000000999'],
            'uuidButNotVersion7' => ['019a6a10-aaaa-4bbb-8ccc-000000000999'],
            'notAUuid' => ['lisbon'],
        ];
    }

    #[DataProvider('idsOfNothing')]
    public function test_AnIdOfNothingIsNotFound(string $id): void
    {
        $response = $this->getJson(self::TRAVEL_REQUESTS . '/' . $id, $this->bearer(accessToken: $this->adminToken));

        $response->assertNotFound()
            ->assertJsonPath('type', '/problems/not-found');
    }

    /**
     * @param array<string, mixed> $travelRequest
     */
    private function pathOf(array $travelRequest): string
    {
        return self::TRAVEL_REQUESTS . '/' . $this->idOf(travelRequest: $travelRequest);
    }
}
