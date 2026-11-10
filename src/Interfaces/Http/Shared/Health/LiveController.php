<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\Health;

use Illuminate\Http\JsonResponse;

final readonly class LiveController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
