<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\Problem;

use Throwable;

/**
 * Filled by each context's provider at boot and only read afterwards, so it is safe to share across requests.
 */
final class ProblemTypeRegistry
{
    /** @var array<class-string<Throwable>, ProblemType> */
    private array $problemTypeByException = [];

    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public function register(string $exceptionClass, ProblemType $problemType): void
    {
        $this->problemTypeByException[$exceptionClass] = $problemType;
    }

    public function find(Throwable $exception): ?ProblemType
    {
        foreach ($this->problemTypeByException as $exceptionClass => $problemType) {
            if ($exception instanceof $exceptionClass) {
                return $problemType;
            }
        }

        return null;
    }
}
