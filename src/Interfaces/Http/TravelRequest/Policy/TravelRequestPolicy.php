<?php

declare(strict_types=1);

namespace Interfaces\Http\TravelRequest\Policy;

use Interfaces\Http\Shared\Auth\AuthenticatedUser;

/**
 * Decides by role only. Who owns a request is checked where the request is loaded, so it is read once.
 */
final readonly class TravelRequestPolicy
{
    public const string APPROVE = 'approve';

    public const string CANCEL = 'cancel';

    public const string VIEW_ALL = 'viewAll';

    public function approve(AuthenticatedUser $user): bool
    {
        return $user->isAdmin;
    }

    public function cancel(AuthenticatedUser $user): bool
    {
        return $user->isAdmin;
    }

    public function viewAll(AuthenticatedUser $user): bool
    {
        return $user->isAdmin;
    }
}
