<?php

declare(strict_types=1);

namespace Application\Shared\Exception;

use InvalidArgumentException;

final class InvalidListCursor extends InvalidArgumentException
{
    public static function unreadable(): self
    {
        return new self('The cursor is not one this list returned.');
    }
}
