<?php

declare(strict_types=1);

namespace Interfaces\Http\Identity\Controller;

use Application\Identity\UseCase\GetUserUseCase;
use Illuminate\Http\Request;
use Interfaces\Http\Identity\Resource\UserResource;
use Interfaces\Http\Shared\Auth\CurrentUser;

final readonly class MeController
{
    public function __construct(
        private GetUserUseCase $getUser,
    ) {}

    public function __invoke(Request $request): UserResource
    {
        $caller = CurrentUser::of(request: $request);

        return new UserResource(user: $this->getUser->execute(userId: $caller->id));
    }
}
