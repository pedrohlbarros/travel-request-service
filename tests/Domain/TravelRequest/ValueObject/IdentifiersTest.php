<?php

declare(strict_types=1);

namespace Tests\Domain\TravelRequest\ValueObject;

use Domain\Shared\Exception\InvalidIdentifier;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother;

final class IdentifiersTest extends TestCase
{
    public function test_TravelRequestIdKeepsCanonicalValue(): void
    {
        $id = TravelRequestId::fromString(id: strtoupper(TravelRequestMother::TRAVEL_REQUEST_ID));
        $sameId = TravelRequestId::fromString(id: TravelRequestMother::TRAVEL_REQUEST_ID);
        $otherId = TravelRequestId::fromString(id: TravelRequestMother::REQUESTER_ID);

        self::assertSame(TravelRequestMother::TRAVEL_REQUEST_ID, $id->toString());
        self::assertTrue($id->equals(other: $sameId));
        self::assertFalse($id->equals(other: $otherId));
    }

    public function test_UserIdKeepsCanonicalValue(): void
    {
        $id = UserId::fromString(id: strtoupper(TravelRequestMother::REQUESTER_ID));
        $sameId = UserId::fromString(id: TravelRequestMother::REQUESTER_ID);
        $otherId = UserId::fromString(id: TravelRequestMother::ADMIN_ID);

        self::assertSame(TravelRequestMother::REQUESTER_ID, $id->toString());
        self::assertTrue($id->equals(other: $sameId));
        self::assertFalse($id->equals(other: $otherId));
    }

    public function test_TravelRequestIdRejectsInvalidUuid(): void
    {
        $this->expectException(InvalidIdentifier::class);

        TravelRequestId::fromString(id: 'not-a-uuid');
    }

    public function test_UserIdRejectsInvalidUuid(): void
    {
        $this->expectException(InvalidIdentifier::class);

        UserId::fromString(id: 'not-a-uuid');
    }
}
