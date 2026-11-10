<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Persistence;

use Infrastructure\Shared\Persistence\BinaryUuid;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BinaryUuidTest extends TestCase
{
    private const string UUID7 = '019a6a20-aaaa-7bbb-8ccc-0123456789ab';

    public function test_RoundTripsThroughSixteenBytes(): void
    {
        $bytes = BinaryUuid::toBytes(uuid: self::UUID7);

        self::assertSame(16, \strlen($bytes));
        self::assertSame(hex2bin('019a6a20aaaa7bbb8ccc0123456789ab'), $bytes);
        self::assertSame(self::UUID7, BinaryUuid::fromBytes(bytes: $bytes));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notUuids(): array
    {
        return [
            'notHex' => ['not-a-uuid'],
            'tooShort' => ['019a6a20-aaaa-7bbb-8ccc'],
        ];
    }

    #[DataProvider('notUuids')]
    public function test_RejectsWhatIsNotAUuid(string $candidate): void
    {
        $this->expectException(InvalidArgumentException::class);

        BinaryUuid::toBytes(uuid: $candidate);
    }

    public function test_RejectsBinaryOfTheWrongLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BinaryUuid::fromBytes(bytes: 'short');
    }
}
