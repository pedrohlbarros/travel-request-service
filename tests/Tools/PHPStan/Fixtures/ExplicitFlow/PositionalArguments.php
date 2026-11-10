<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan\Fixtures\ExplicitFlow;

use DateTimeImmutable;
use Illuminate\Support\Str;

final class PositionalArguments
{
    public function __construct(
        private readonly string $label = '',
    ) {}

    public static function make(string $label): self
    {
        return new self($label);
    }

    public function ownCode(): string
    {
        $made = self::make('label');

        return $made->describe('prefix');
    }

    public function exempt(string ...$parts): string
    {
        $moment = new DateTimeImmutable('now');
        $slug = Str::slug('Hello World');
        $joined = $this->join(...$parts);
        $variadic = $this->join('a', 'b');

        return $moment->format('Y') . $slug . $joined . $variadic . $this->describe(prefix: 'named');
    }

    public function describe(string $prefix): string
    {
        return $prefix . $this->label;
    }

    public function join(string ...$parts): string
    {
        return implode('', $parts);
    }
}
