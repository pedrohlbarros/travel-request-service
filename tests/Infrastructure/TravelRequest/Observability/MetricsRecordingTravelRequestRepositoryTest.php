<?php

declare(strict_types=1);

namespace Tests\Infrastructure\TravelRequest\Observability;

use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Repository\TravelRequestRepository;
use Domain\TravelRequest\ValueObject\Destination;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\LaravelTestCase;
use Tests\Support\Shared\Observability\CapturesTelemetry;
use Tests\Support\TravelRequest\Fixture\StoresFixtureUsers;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother as Mother;

final class MetricsRecordingTravelRequestRepositoryTest extends LaravelTestCase
{
    use CapturesTelemetry;
    use RefreshDatabase;
    use StoresFixtureUsers;

    private TravelRequestRepository $travelRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storeFixtureUsers();
        $this->captureTelemetry();
        $this->travelRequests = $this->app->make(TravelRequestRepository::class);
    }

    protected function tearDown(): void
    {
        $this->releaseTelemetry();
        parent::tearDown();
    }

    public function test_CountsEachCreatedRequest(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());

        self::assertSame(1, $this->capturedValue(metricName: 'travel_request.created'));
    }

    public function test_CountsDecisionsByTheirStatus(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());
        $travelRequest = $this->travelRequests->get(id: Mother::travelRequestId());
        $travelRequest->approve(
            approverId: Mother::userId(id: Mother::ADMIN_ID),
            approvedAt: Mother::moment(moment: Mother::LATER),
        );

        $this->travelRequests->update(travelRequest: $travelRequest, loadedVersion: TravelRequest::FIRST_VERSION);

        self::assertSame(
            1,
            $this->capturedValue(metricName: 'travel_request.transitions', attributes: ['status' => 'approved']),
        );
    }

    public function test_AnEditIsNotATransition(): void
    {
        $this->travelRequests->add(travelRequest: Mother::requested());
        $travelRequest = $this->travelRequests->get(id: Mother::travelRequestId());
        $travelRequest->changeDetails(
            editorId: Mother::userId(id: Mother::REQUESTER_ID),
            destination: Destination::fromString(name: 'Tokyo'),
            departureDate: null,
            returnDate: null,
            changedAt: Mother::moment(moment: Mother::LATER),
        );

        $this->travelRequests->update(travelRequest: $travelRequest, loadedVersion: TravelRequest::FIRST_VERSION);

        self::assertSame([], $this->capturedPoints(metricName: 'travel_request.transitions'));
    }

    public function test_NothingIsCountedForAWriteThatIsRolledBack(): void
    {
        try {
            $this->app->make(ConnectionInterface::class)
                ->transaction(function (): void {
                    $this->travelRequests->add(travelRequest: Mother::requested());

                    throw new RuntimeException('Rolled back.');
                });
        } catch (RuntimeException) {
        }

        self::assertSame([], $this->capturedPoints(metricName: 'travel_request.created'));
    }
}
