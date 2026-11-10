<?php

declare(strict_types=1);

namespace Tests\Domain\TravelRequest\Enum;

use Domain\TravelRequest\Enum\TravelRequestStatus;
use PHPUnit\Framework\TestCase;

final class TravelRequestStatusTest extends TestCase
{
    public function test_StatusValuesAreTheApiContract(): void
    {
        $statusValues = array_map(
            static fn(TravelRequestStatus $status): string => $status->value,
            TravelRequestStatus::cases(),
        );

        self::assertSame(['requested', 'approved', 'cancelled'], $statusValues);
    }
}
