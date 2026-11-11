<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Query;

use Application\Shared\Exception\InvalidListCursor;
use Domain\Shared\Exception\InvalidIdentifier;
use Domain\Shared\ValueObject\Uuid7;
use JsonException;

/**
 * Keyset position in a list ordered by id: the id at the edge of a page and which way to go from it.
 * Encoded as URL-safe base64 of a small JSON object, so clients treat it as opaque.
 */
final readonly class ListCursor
{
    private const string BASE64_ALPHABET = '+/';

    private const string URL_SAFE_ALPHABET = '-_';

    private const string BASE64_PADDING = '=';

    private const int JSON_DEPTH = 2;

    private function __construct(
        public Uuid7 $edgeId,
        public ListDirection $direction,
    ) {}

    public static function olderThan(string $edgeId): self
    {
        return new self(edgeId: Uuid7::fromString(candidate: $edgeId), direction: ListDirection::Older);
    }

    public static function newerThan(string $edgeId): self
    {
        return new self(edgeId: Uuid7::fromString(candidate: $edgeId), direction: ListDirection::Newer);
    }

    /**
     * @throws InvalidListCursor
     */
    public static function decode(string $encodedCursor): self
    {
        $position = self::positionFrom(encodedCursor: $encodedCursor);
        $edgeId = $position['id'] ?? null;
        $directionName = $position['direction'] ?? null;
        $direction = \is_string($directionName) ? ListDirection::tryFrom(value: $directionName) : null;

        if (! \is_string($edgeId) || $direction === null) {
            throw InvalidListCursor::unreadable();
        }

        try {
            return new self(edgeId: Uuid7::fromString(candidate: $edgeId), direction: $direction);
        } catch (InvalidIdentifier) {
            throw InvalidListCursor::unreadable();
        }
    }

    public function encode(): string
    {
        $position = ['id' => $this->edgeId->value, 'direction' => $this->direction->value];
        $base64 = base64_encode(json_encode($position, JSON_THROW_ON_ERROR));

        return rtrim(strtr($base64, self::BASE64_ALPHABET, self::URL_SAFE_ALPHABET), self::BASE64_PADDING);
    }

    /**
     * @return array<mixed>
     */
    private static function positionFrom(string $encodedCursor): array
    {
        $base64 = strtr($encodedCursor, self::URL_SAFE_ALPHABET, self::BASE64_ALPHABET);
        $json = base64_decode($base64, true);

        if ($json === false) {
            return [];
        }

        try {
            $position = json_decode(
                $json,
                true,
                self::JSON_DEPTH,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return [];
        }

        return \is_array($position) ? $position : [];
    }
}
