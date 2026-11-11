<?php

declare(strict_types=1);

namespace Tests\Infrastructure\TravelRequest\Query;

use Application\TravelRequest\Exception\InvalidListCursor;
use Infrastructure\TravelRequest\Query\ListCursor;
use Infrastructure\TravelRequest\Query\ListDirection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListCursorTest extends TestCase
{
    private const string EDGE_ID = '019a6a10-aaaa-7bbb-8ccc-000000000001';

    private const string URL_SAFE_BASE64 = '/\A[A-Za-z0-9_-]+\z/';

    public function test_ReadsBackWhatItEncoded(): void
    {
        $olderCursor = ListCursor::olderThan(edgeId: self::EDGE_ID);
        $newerCursor = ListCursor::newerThan(edgeId: self::EDGE_ID);

        $decodedOlder = ListCursor::decode(encodedCursor: $olderCursor->encode());
        $decodedNewer = ListCursor::decode(encodedCursor: $newerCursor->encode());

        self::assertSame(self::EDGE_ID, $decodedOlder->edgeId->toString());
        self::assertSame(ListDirection::Older, $decodedOlder->direction);
        self::assertSame(ListDirection::Newer, $decodedNewer->direction);
    }

    public function test_EncodesAsUrlSafeBase64WithoutPadding(): void
    {
        $cursor = ListCursor::olderThan(edgeId: self::EDGE_ID);

        $encodedCursor = $cursor->encode();

        self::assertMatchesRegularExpression(self::URL_SAFE_BASE64, $encodedCursor);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreadableCursors(): array
    {
        return [
            'notBase64' => ['%%%'],
            'notJson' => [self::encoded(json: 'not json')],
            'notAnObject' => [self::encoded(json: '"text"')],
            'missingId' => [self::encoded(json: '{"direction":"older"}')],
            'idNotText' => [self::encoded(json: '{"id":1,"direction":"older"}')],
            'idNotUuid7' => [self::encoded(json: '{"id":"abc","direction":"older"}')],
            'unknownDirection' => [self::encoded(json: '{"id":"' . self::EDGE_ID . '","direction":"sideways"}')],
            'directionNotText' => [self::encoded(json: '{"id":"' . self::EDGE_ID . '","direction":1}')],
            'tooDeep' => [self::encoded(json: '{"id":{"nested":["x"]},"direction":"older"}')],
        ];
    }

    #[DataProvider('unreadableCursors')]
    public function test_RejectsAnUnreadableCursor(string $encodedCursor): void
    {
        $this->expectException(InvalidListCursor::class);
        $this->expectExceptionMessageIs('The cursor is not one this list returned.');

        ListCursor::decode(encodedCursor: $encodedCursor);
    }

    private static function encoded(string $json): string
    {
        $urlSafe = strtr(base64_encode($json), '+/', '-_');

        return rtrim($urlSafe, '=');
    }
}
