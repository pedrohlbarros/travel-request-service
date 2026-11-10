<?php

declare(strict_types=1);

namespace Domain\TravelRequest\Enum;

enum TravelRequestStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Cancelled = 'cancelled';
}
