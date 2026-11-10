<?php

declare(strict_types=1);

namespace Interfaces\Http\Identity\Controller;

use Application\Identity\UseCase\LoginUseCase;
use Interfaces\Http\Identity\Request\LoginRequest;
use Interfaces\Http\Identity\Resource\TokenPairResource;

final readonly class LoginController
{
    public function __construct(
        private LoginUseCase $login,
    ) {}

    public function __invoke(LoginRequest $request): TokenPairResource
    {
        $tokenPair = $this->login->execute(input: $request->toInput());

        return new TokenPairResource(tokenPair: $tokenPair);
    }
}
