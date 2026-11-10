<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan\Fixtures\Naming;

use Throwable;

final class DescriptiveNames
{
    public function __construct(
        private readonly string $id,
    ) {}

    public function run(string $ip): string
    {
        $attempts = 0;

        for ($i = 0; $i < 3; $i++) {
            $attempts++;
        }

        foreach ([$ip] as $position => $clientAddress) {
            unset($position, $clientAddress);
        }

        try {
        } catch (Throwable $exception) {
            unset($exception);
        }

        return $this->id . $attempts;
    }
}
