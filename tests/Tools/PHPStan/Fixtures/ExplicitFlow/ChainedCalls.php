<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan\Fixtures\ExplicitFlow;

final class ChainedCalls
{
    private ?self $next = null;

    public function sameLine(): self
    {
        return $this->first()->second();
    }

    public function split(): self
    {
        return $this->first()
            ->second();
    }

    public function oneCallOnAProperty(): ?self
    {
        return $this->next?->first();
    }

    private function first(): self
    {
        return $this;
    }

    private function second(): self
    {
        return $this;
    }
}
