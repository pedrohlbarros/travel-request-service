<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\Auth;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

final readonly class CurrentUser
{
    /**
     * @throws AuthenticationException
     */
    public static function of(Request $request): AuthenticatedUser
    {
        $resolveUser = $request->getUserResolver();
        $caller = $resolveUser();

        if (! $caller instanceof AuthenticatedUser) {
            throw new AuthenticationException();
        }

        return $caller;
    }
}
