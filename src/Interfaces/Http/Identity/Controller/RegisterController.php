<?php

declare(strict_types=1);

namespace Interfaces\Http\Identity\Controller;

use Application\Identity\UseCase\CreateUserUseCase;
use Illuminate\Http\JsonResponse;
use Interfaces\Http\Identity\Request\RegisterRequest;
use Interfaces\Http\Identity\Resource\UserResource;
use Symfony\Component\HttpFoundation\Response;

final readonly class RegisterController
{
    public function __construct(
        private CreateUserUseCase $createUser,
    ) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = $this->createUser->execute(input: $request->toInput());

        return new UserResource(user: $user)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
