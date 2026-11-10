<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan\Fixtures\ExplicitFlow;

final class NestedCalls
{
    public function threeLevels(string $text): string
    {
        return trim(strtolower(ucfirst($text)));
    }

    public function twoLevelsAndAChain(string $text): string
    {
        $shout = strtoupper(trim($text));

        return $this->wrap($shout)->value();
    }

    public function closuresStartAgain(array $texts): array
    {
        return array_map(static fn(string $text): string => trim(strtolower($text)), $texts);
    }

    private function wrap(string $text): self
    {
        unset($text);

        return $this;
    }

    private function value(): string
    {
        return '';
    }
}
