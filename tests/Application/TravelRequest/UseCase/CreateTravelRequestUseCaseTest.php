<?php

declare(strict_types=1);

namespace Tests\Application\TravelRequest\UseCase;

use Application\TravelRequest\Input\CreateTravelRequestInput;
use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\UseCase\CreateTravelRequestUseCase;
use Domain\Shared\Exception\InvalidIdentifier;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\Exception\InvalidDestination;
use Domain\TravelRequest\Exception\InvalidTravelPeriod;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use PHPUnit\Framework\TestCase;
use Tests\Support\Shared\Port\FrozenClock;
use Tests\Support\Shared\Port\SequentialIdentifierGenerator;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother as Mother;
use Tests\Support\TravelRequest\Repository\InMemoryTravelRequestRepository;

final class CreateTravelRequestUseCaseTest extends TestCase
{
    private InMemoryTravelRequestRepository $travelRequests;

    private CreateTravelRequestUseCase $useCase;

    protected function setUp(): void
    {
        $clock = new FrozenClock(frozenAt: Mother::moment(moment: Mother::NOW));
        $this->travelRequests = new InMemoryTravelRequestRepository();
        $this->useCase = new CreateTravelRequestUseCase(
            travelRequests: $this->travelRequests,
            identifiers: new SequentialIdentifierGenerator(),
            clock: $clock,
        );
    }

    public function test_UserCreatesTravelRequestForThemselves(): void
    {
        $input = new CreateTravelRequestInput(
            requesterId: Mother::REQUESTER_ID,
            destination: '  ' . Mother::DESTINATION . ' ',
            departureDate: Mother::moment(moment: Mother::DEPARTURE_DATE),
            returnDate: Mother::moment(moment: Mother::RETURN_DATE),
        );

        $created = $this->useCase->execute(input: $input);

        $stored = $this->travelRequests->get(id: TravelRequestId::fromString(id: $created->id));

        self::assertEquals(
            TravelRequestOutput::fromEntity(travelRequest: $stored),
            $created,
        );
        self::assertSame(
            SequentialIdentifierGenerator::nth(sequence: 1),
            $created->id,
        );
        self::assertSame(
            Mother::REQUESTER_ID,
            $stored->requesterId->toString(),
        );
        self::assertSame(
            Mother::DESTINATION,
            $stored->destination->name,
        );
        self::assertEquals(
            Mother::period(),
            $stored->period,
        );
        self::assertSame(
            TravelRequestStatus::Requested,
            $stored->status,
        );
        self::assertSame(
            TravelRequest::FIRST_VERSION,
            $stored->version,
        );
        self::assertEquals(
            Mother::moment(moment: Mother::NOW),
            $stored->createdAt,
        );
    }

    public function test_EachTravelRequestGetsItsOwnId(): void
    {
        $input = new CreateTravelRequestInput(
            requesterId: Mother::REQUESTER_ID,
            destination: Mother::DESTINATION,
            departureDate: Mother::moment(moment: Mother::DEPARTURE_DATE),
            returnDate: Mother::moment(moment: Mother::RETURN_DATE),
        );

        $first = $this->useCase->execute(input: $input);
        $second = $this->useCase->execute(input: $input);

        self::assertNotSame(
            $first->id,
            $second->id,
        );
    }

    public function test_RejectsInvalidRequesterId(): void
    {
        $this->expectException(InvalidIdentifier::class);

        $input = new CreateTravelRequestInput(
            requesterId: 'not-a-uuid',
            destination: Mother::DESTINATION,
            departureDate: Mother::moment(moment: Mother::DEPARTURE_DATE),
            returnDate: Mother::moment(moment: Mother::RETURN_DATE),
        );

        $this->useCase->execute(input: $input);
    }

    public function test_RejectsInvalidDestination(): void
    {
        $this->expectException(InvalidDestination::class);

        $input = new CreateTravelRequestInput(
            requesterId: Mother::REQUESTER_ID,
            destination: ' ',
            departureDate: Mother::moment(moment: Mother::DEPARTURE_DATE),
            returnDate: Mother::moment(moment: Mother::RETURN_DATE),
        );

        $this->useCase->execute(input: $input);
    }

    public function test_RejectsDepartureBeforeToday(): void
    {
        $this->expectException(InvalidTravelPeriod::class);

        $input = new CreateTravelRequestInput(
            requesterId: Mother::REQUESTER_ID,
            destination: Mother::DESTINATION,
            departureDate: Mother::moment(moment: '2026-11-08'),
            returnDate: Mother::moment(moment: Mother::RETURN_DATE),
        );

        $this->useCase->execute(input: $input);
    }
}
