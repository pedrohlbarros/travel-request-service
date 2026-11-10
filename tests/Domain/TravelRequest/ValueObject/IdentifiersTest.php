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
        $id = TravelRequestId::fromString(strtoupper(TravelRequestMother::TRAVEL_REQUEST_ID));

        self::assertSame(TravelRequestMother::TRAVEL_REQUEST_ID, $id->toString());
        self::assertTrue($id->equals(TravelRequestId::fromString(TravelRequestMother::TRAVEL_REQUEST_ID)));
        self::assertFalse($id->equals(TravelRequestId::fromString(TravelRequestMother::REQUESTER_ID)));
    }

    public function test_UserIdKeepsCanonicalValue(): void
    {
        $id = UserId::fromString(strtoupper(TravelRequestMother::REQUESTER_ID));

        self::assertSame(TravelRequestMother::REQUESTER_ID, $id->toString());
        self::assertTrue($id->equals(UserId::fromString(TravelRequestMother::REQUESTER_ID)));
        self::assertFalse($id->equals(UserId::fromString(TravelRequestMother::ADMIN_ID)));
    }

    public function test_TravelRequestIdRejectsInvalidUuid(): void
    {
        $this->expectException(InvalidIdentifier::class);

        TravelRequestId::fromString('not-a-uuid');
    }

    public function test_UserIdRejectsInvalidUuid(): void
    {
        $this->expectException(InvalidIdentifier::class);

        UserId::fromString('not-a-uuid');
    }
}
