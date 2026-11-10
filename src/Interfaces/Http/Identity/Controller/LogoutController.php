<?php

declare(strict_types=1);

namespace Interfaces\Http\Identity\Controller;

use Application\Identity\Input\LogoutInput;
use Application\Identity\UseCase\LogoutUseCase;
use Illuminate\Http\Response;
use Interfaces\Http\Identity\Request\RefreshTokenRequest;
use Interfaces\Http\Shared\Auth\CurrentUser;

final readonly class LogoutController
{
    public function __construct(
        private LogoutUseCase $logout,
    ) {}

    public function __invoke(RefreshTokenRequest $request): Response
    {
        $caller = CurrentUser::of(request: $request);

        $logout = new LogoutInput(
            userId: $caller->id,
            accessTokenId: $caller->accessTokenId,
            accessTokenExpiresAt: $caller->accessTokenExpiresAt,
            refreshToken: $request->refreshToken(),
        );

        $this->logout->execute(input: $logout);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
