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
}
