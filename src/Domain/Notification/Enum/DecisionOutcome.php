<?php

declare(strict_types=1);

namespace Domain\Notification\Enum;

enum DecisionOutcome: string
{
    case Approved = 'approved';
    case Cancelled = 'cancelled';
}
