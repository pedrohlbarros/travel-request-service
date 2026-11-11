<?php

declare(strict_types=1);

namespace Tests\Domain\Notification\ValueObject;

use Domain\Notification\Exception\InvalidDecision;
use Domain\Notification\ValueObject\Recipient;
use Domain\Shared\Exception\InvalidIdentifier;
use PHPUnit\Framework\TestCase;
use Tests\Support\Notification\Fixture\DecisionMother as Mother;

final class RecipientTest extends TestCase
{
    public function test_KeepsWhoTheDecisionIsFor(): void
    {
        $recipient = Recipient::of(id: Mother::REQUESTER_ID, name: '  Monkey D. Luffy  ', email: Mother::REQUESTER_EMAIL);

        self::assertSame(Mother::REQUESTER_ID, $recipient->id->value);
        self::assertSame(Mother::REQUESTER_NAME, $recipient->name);
        self::assertSame(Mother::REQUESTER_EMAIL, $recipient->email);
    }

    public function test_RequiresAName(): void
    {
        $this->expectException(InvalidDecision::class);
        $this->expectExceptionMessageIs('The decision has no requester name.');

        Recipient::of(id: Mother::REQUESTER_ID, name: '   ', email: Mother::REQUESTER_EMAIL);
    }

    public function test_RequiresAnEmailAddress(): void
    {
        $this->expectException(InvalidDecision::class);
        $this->expectExceptionMessageIs('"not-an-email" is not an e-mail address.');

        Recipient::of(id: Mother::REQUESTER_ID, name: Mother::REQUESTER_NAME, email: 'not-an-email');
    }

    public function test_RequiresAUuid7Id(): void
    {
        $this->expectException(InvalidIdentifier::class);

        Recipient::of(id: 'not-a-uuid', name: Mother::REQUESTER_NAME, email: Mother::REQUESTER_EMAIL);
    }
}
