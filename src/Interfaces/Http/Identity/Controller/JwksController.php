<?php

declare(strict_types=1);

namespace Interfaces\Http\Identity\Controller;

use Application\Identity\UseCase\GetJsonWebKeySetUseCase;
use Illuminate\Http\JsonResponse;

final readonly class JwksController
{
    private const string CACHE_CONTROL = 'public, max-age=300';

    public function __construct(
        private GetJsonWebKeySetUseCase $getKeySet,
    ) {}

    public function __invoke(): JsonResponse
    {
        return new JsonResponse(
            ['keys' => $this->getKeySet->execute()->keys],
            headers: ['Cache-Control' => self::CACHE_CONTROL],
        );
    }
}
