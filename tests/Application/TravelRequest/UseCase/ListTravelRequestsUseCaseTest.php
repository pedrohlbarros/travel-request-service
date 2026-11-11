<?php

declare(strict_types=1);

namespace Tests\Application\TravelRequest\UseCase;

use Application\TravelRequest\Input\ListTravelRequestsInput;
use Application\TravelRequest\Input\TravelRequestFilters;
use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\Output\TravelRequestPage;
use Application\TravelRequest\UseCase\ListTravelRequestsUseCase;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\ValueObject\Destination;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use PHPUnit\Framework\TestCase;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother as Mother;
use Tests\Support\TravelRequest\Port\InMemoryTravelRequestQueries;
use Tests\Support\TravelRequest\Repository\InMemoryTravelRequestRepository;

final class ListTravelRequestsUseCaseTest extends TestCase
{
    private const bool AS_ADMIN = true;

    private const bool AS_USER = false;

    private const string OTHER_TRAVEL_REQUEST_ID = '019a6a10-aaaa-7bbb-8ccc-000000000002';

    private const int PER_PAGE = 20;

    private ListTravelRequestsUseCase $useCase;

    protected function setUp(): void
    {
        $travelRequests = new InMemoryTravelRequestRepository();
        $travelRequests->add(travelRequest: Mother::requested());
        $travelRequests->add(travelRequest: $this->requestedBy(
            id: self::OTHER_TRAVEL_REQUEST_ID,
            requesterId: Mother::OTHER_USER_ID,
        ));
        $queries = new InMemoryTravelRequestQueries(travelRequests: $travelRequests);
        $this->useCase = new ListTravelRequestsUseCase(queries: $queries);
    }

    public function test_UserListsOnlyOwnTravelRequests(): void
    {
        $page = $this->listAs(viewerId: Mother::REQUESTER_ID, isViewerAdmin: self::AS_USER);

        $listedIds = $this->idsOf(page: $page);

        self::assertSame([Mother::TRAVEL_REQUEST_ID], $listedIds);
    }

    public function test_AdminListsEveryonesTravelRequests(): void
    {
        $page = $this->listAs(viewerId: Mother::ADMIN_ID, isViewerAdmin: self::AS_ADMIN);

        $listedIds = $this->idsOf(page: $page);

        self::assertSame([self::OTHER_TRAVEL_REQUEST_ID, Mother::TRAVEL_REQUEST_ID], $listedIds);
        self::assertSame(self::PER_PAGE, $page->perPage);
    }

    private function listAs(string $viewerId, bool $isViewerAdmin): TravelRequestPage
    {
        $input = new ListTravelRequestsInput(
            viewerId: $viewerId,
            isViewerAdmin: $isViewerAdmin,
            filters: new TravelRequestFilters(),
            cursor: null,
            perPage: self::PER_PAGE,
        );

        return $this->useCase->execute(input: $input);
    }

    /**
     * @return list<string>
     */
    private function idsOf(TravelRequestPage $page): array
    {
        return array_map(
            static fn(TravelRequestOutput $travelRequest): string => $travelRequest->id,
            $page->travelRequests,
        );
    }

    private function requestedBy(string $id, string $requesterId): TravelRequest
    {
        return TravelRequest::request(
            id: TravelRequestId::fromString(id: $id),
            requesterId: Mother::userId(id: $requesterId),
            destination: Destination::fromString(name: Mother::DESTINATION),
            period: Mother::period(),
            requestedAt: Mother::moment(moment: Mother::NOW),
        );
    }
}
