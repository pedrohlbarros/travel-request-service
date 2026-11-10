<?php

declare(strict_types=1);

namespace Application\Identity\UseCase;

use Application\Identity\Output\JsonWebKeySetOutput;
use Application\Identity\Port\JsonWebKeySet;

final readonly class GetJsonWebKeySetUseCase
{
    public function __construct(
        private JsonWebKeySet $keySet,
    ) {}

    public function execute(): JsonWebKeySetOutput
    {
        return new JsonWebKeySetOutput(keys: $this->keySet->publicKeys());
    }
}
