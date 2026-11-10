<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan\Fixtures\ExplicitFlow;

final class ArgumentLayout
{
    public function fourOnOneLine(): string
    {
        return $this->four('a', 'b', 'c', 'd');
    }

    public function fourSplit(): string
    {
        return $this->four(
            'a',
            'b',
            'c',
            'd',
        );
    }

    public function shortButSplit(): string
    {
        return str_replace(
            'a',
            'b',
            'abc',
        );
    }

    public function shortOnOneLine(): string
    {
        return str_replace('a', 'b', 'abc');
    }

    public function longOnOneLine(): string
    {
        return str_replace('a-very-long-search-string-that-pushes-the-line', 'a-long-replacement-string-for-it', 'subject');
    }

    public function singleLongArgumentStays(): string
    {
        return strtoupper('a single argument long enough to push this line far past the one hundred and twenty columns');
    }

    public function four(string $first, string $second, string $third, string $fourth): string
    {
        return $first . $second . $third . $fourth;
    }
}
