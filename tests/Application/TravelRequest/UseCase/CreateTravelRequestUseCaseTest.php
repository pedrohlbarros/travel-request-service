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
        $this->travelRequests = new InMemoryTravelRequestRepository();
        $this->useCase = new CreateTravelRequestUseCase(
            $this->travelRequests,
            new SequentialIdentifierGenerator(),
            new FrozenClock(Mother::moment(Mother::NOW)),
        );
    }

    public function test_UserCreatesTravelRequestForThemselves(): void
    {
        $input = new CreateTravelRequestInput(
            Mother::REQUESTER_ID,
            '  ' . Mother::DESTINATION . ' ',
            Mother::moment(Mother::DEPARTURE_DATE),
            Mother::moment(Mother::RETURN_DATE),
        );

        $created = $this->useCase->execute($input);

        $stored = $this->travelRequests->get(TravelRequestId::fromString($created->id));
        self::assertEquals(TravelRequestOutput::fromEntity($stored), $created);
        self::assertSame(SequentialIdentifierGenerator::nth(1), $created->id);
        self::assertSame(Mother::REQUESTER_ID, $stored->requesterId->toString());
        self::assertSame(Mother::DESTINATION, $stored->destination->name);
        self::assertEquals(Mother::period(), $stored->period);
        self::assertSame(TravelRequestStatus::Requested, $stored->status);
        self::assertSame(TravelRequest::FIRST_VERSION, $stored->version);
        self::assertEquals(Mother::moment(Mother::NOW), $stored->createdAt);
    }

    public function test_EachTravelRequestGetsItsOwnId(): void
    {
        $input = new CreateTravelRequestInput(
            Mother::REQUESTER_ID,
            Mother::DESTINATION,
            Mother::moment(Mother::DEPARTURE_DATE),
            Mother::moment(Mother::RETURN_DATE),
        );

        $first = $this->useCase->execute($input);
        $second = $this->useCase->execute($input);

        self::assertNotSame($first->id, $second->id);
    }

    public function test_RejectsInvalidRequesterId(): void
    {
        $this->expectException(InvalidIdentifier::class);

        $this->useCase->execute(new CreateTravelRequestInput(
            'not-a-uuid',
            Mother::DESTINATION,
            Mother::moment(Mother::DEPARTURE_DATE),
            Mother::moment(Mother::RETURN_DATE),
        ));
    }

    public function test_RejectsInvalidDestination(): void
    {
        $this->expectException(InvalidDestination::class);

        $this->useCase->execute(new CreateTravelRequestInput(
            Mother::REQUESTER_ID,
            ' ',
            Mother::moment(Mother::DEPARTURE_DATE),
            Mother::moment(Mother::RETURN_DATE),
        ));
    }

    public function test_RejectsDepartureBeforeToday(): void
    {
        $this->expectException(InvalidTravelPeriod::class);

        $this->useCase->execute(new CreateTravelRequestInput(
            Mother::REQUESTER_ID,
            Mother::DESTINATION,
            Mother::moment('2026-11-08'),
            Mother::moment(Mother::RETURN_DATE),
        ));
    }
}
