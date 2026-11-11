<?php

declare(strict_types=1);

namespace Tests\Support\Notification\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Stringable;

final class RecordingBroadcaster extends Broadcaster
{
    public const string FAILURE = 'Reverb is unreachable.';

    /** @var list<array{channels: list<string>, event: string, payload: array<mixed>}> */
    public array $broadcasts = [];

    public function __construct(
        private readonly bool $isFailing = false,
    ) {}

    public function auth($request): mixed
    {
        return null;
    }

    public function validAuthenticationResponse($request, $result): mixed
    {
        return $result;
    }

    /**
     * @param array<mixed> $channels
     * @param array<mixed> $payload
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        if ($this->isFailing) {
            throw new BroadcastException(self::FAILURE);
        }

        $channelNames = [];

        foreach ($channels as $channel) {
            $channelNames[] = $channel instanceof Stringable || \is_string($channel) ? (string) $channel : '';
        }

        $this->broadcasts[] = ['channels' => $channelNames, 'event' => $event, 'payload' => $payload];
    }
}
