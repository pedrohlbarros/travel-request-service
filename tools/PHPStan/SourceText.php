<?php

declare(strict_types=1);

namespace Tools\PHPStan;

/**
 * Source of the analysed files, read once per file, for rules that measure how code is laid out.
 */
final class SourceText
{
    /** @var array<string, string> */
    private static array $contentsByFile = [];

    public static function of(string $file): string
    {
        if (! isset(self::$contentsByFile[$file])) {
            $contents = file_get_contents($file);
            self::$contentsByFile[$file] = $contents === false ? '' : $contents;
        }

        return self::$contentsByFile[$file];
    }
}
