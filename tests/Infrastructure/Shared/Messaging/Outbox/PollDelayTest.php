<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Messaging\Outbox;

use Infrastructure\Shared\Messaging\Outbox\PollDelay;
use PHPUnit\Framework\TestCase;

final class PollDelayTest extends TestCase
{
    private const int MIN_MILLISECONDS = 200;

    private const int MAX_MILLISECONDS = 2000;

    public function test_StartsAtTheMinimum(): void
    {
        $pollDelay = $this->pollDelay();

        self::assertSame(self::MIN_MILLISECONDS, $pollDelay->milliseconds);
    }

    public function test_DoublesWhileNothingIsPublishedUpToTheMaximum(): void
    {
        $pollDelay = $this->pollDelay();
        $delays = [];

        for ($idleBatch = 0; $idleBatch < 5; $idleBatch++) {
            $pollDelay = $pollDelay->afterBatch(publishedMessages: 0);
            $delays[] = $pollDelay->milliseconds;
        }

        self::assertSame([400, 800, 1600, 2000, 2000], $delays);
    }

    public function test_ABatchWithMessagesBringsItBackToTheMinimum(): void
    {
        $backedOff = $this->pollDelay()
            ->backedOff()
            ->backedOff();

        $afterWork = $backedOff->afterBatch(publishedMessages: 1);

        self::assertSame(800, $backedOff->milliseconds);
        self::assertSame(self::MIN_MILLISECONDS, $afterWork->milliseconds);
    }

    private function pollDelay(): PollDelay
    {
        return PollDelay::startingAt(minMilliseconds: self::MIN_MILLISECONDS, maxMilliseconds: self::MAX_MILLISECONDS);
    }
}
