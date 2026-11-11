<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Config;

use Illuminate\Contracts\Config\Repository as Config;
use InvalidArgumentException;

/**
 * Reads config values with the type an adapter needs, failing at boot instead of on the first request.
 */
final readonly class TypedConfig
{
    private const string LIST_SEPARATOR = ',';

    /**
     * @return non-empty-string
     */
    public static function nonEmptyString(Config $config, string $key): string
    {
        $value = $config->get($key);

        if (! \is_string($value) || $value === '') {
            $message = \sprintf('Config "%s" must be a non-empty string.', $key);

            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    /**
     * @return positive-int
     */
    public static function positiveInt(Config $config, string $key): int
    {
        $value = $config->get($key);
        $number = is_numeric($value) ? (int) $value : 0;

        if ($number <= 0) {
            $message = \sprintf('Config "%s" must be a positive number.', $key);

            throw new InvalidArgumentException($message);
        }

        return $number;
    }

    /**
     * A comma-separated list of positive numbers, such as "10000,60000".
     *
     * @return non-empty-list<positive-int>
     */
    public static function positiveIntList(Config $config, string $key): array
    {
        $value = $config->get($key);
        $items = explode(self::LIST_SEPARATOR, \is_string($value) ? $value : '');
        $numbers = [];

        foreach ($items as $item) {
            $trimmedItem = trim($item);
            $number = ctype_digit($trimmedItem) ? (int) $trimmedItem : 0;

            if ($number <= 0) {
                $message = \sprintf('Config "%s" must be a comma-separated list of positive numbers.', $key);

                throw new InvalidArgumentException($message);
            }

            $numbers[] = $number;
        }

        return $numbers;
    }
}
