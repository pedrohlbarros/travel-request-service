<?php

declare(strict_types=1);

namespace Tests\Domain\Identity\ValueObject;

use Domain\Identity\Exception\InvalidEmail;
use Domain\Identity\ValueObject\Email;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    private const int LONGEST_LOCAL_PART = 64;

    private const int LONGEST_DOMAIN_LABEL = 63;

    public function test_NormalizesToTrimmedLowercase(): void
    {
        $email = Email::fromString(address: '  Luffy@Example.COM ');

        self::assertSame('luffy@example.com', $email->address);
    }

    public function test_AcceptsTheLongestAllowedAddress(): void
    {
        $longestAddress = self::addressOfLength(length: Email::LONGEST_ADDRESS);

        $email = Email::fromString(address: $longestAddress);

        self::assertSame(Email::LONGEST_ADDRESS, \strlen($email->address));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedAddresses(): array
    {
        return [
            'empty' => [''],
            'withoutAt' => ['luffy.example.com'],
            'withoutDomain' => ['luffy@'],
            'withSpaceInside' => ['luf fy@example.com'],
            'tooLong' => [self::addressOfLength(length: Email::LONGEST_ADDRESS + 1)],
        ];
    }

    #[DataProvider('malformedAddresses')]
    public function test_RejectsMalformedAddresses(string $address): void
    {
        $this->expectException(InvalidEmail::class);

        Email::fromString(address: $address);
    }

    public function test_EqualsSameNormalizedAddress(): void
    {
        $email = Email::fromString(address: 'luffy@example.com');
        $sameAddressInUppercase = Email::fromString(address: 'LUFFY@example.com');
        $otherAddress = Email::fromString(address: 'zoro@example.com');

        self::assertTrue($email->equals(other: $sameAddressInUppercase));
        self::assertFalse($email->equals(other: $otherAddress));
    }

    /**
     * A well-formed address of the given length: the longest local part and domain labels the RFCs allow, with the
     * last label filling the rest.
     */
    private static function addressOfLength(int $length): string
    {
        $localPart = str_repeat('a', self::LONGEST_LOCAL_PART);
        $label = str_repeat('b', self::LONGEST_DOMAIN_LABEL);
        $leadingLabels = $label . '.' . $label;
        $lastLabelLength = $length - \strlen($localPart) - \strlen($leadingLabels) - \strlen('@.');
        $lastLabel = str_repeat('c', $lastLabelLength);

        return $localPart . '@' . $leadingLabels . '.' . $lastLabel;
    }
}
