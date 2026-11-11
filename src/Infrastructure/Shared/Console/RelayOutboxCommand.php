<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use Infrastructure\Shared\Messaging\Outbox\OutboxRelay;
use Infrastructure\Shared\Messaging\Outbox\OutboxRelaySettings;
use Infrastructure\Shared\Messaging\Outbox\PollDelay;
use Psr\Log\LoggerInterface;
use Throwable;

final class RelayOutboxCommand extends Command
{
    protected $signature = 'outbox:relay {--once : Relay a single batch and exit}';

    protected $description = 'Publish the pending outbox messages to RabbitMQ';

    private bool $isStopping = false;

    public function handle(OutboxRelay $relay, OutboxRelaySettings $settings, LoggerInterface $logger): int
    {
        if ($this->option('once') === true) {
            $publishedMessages = $this->relayBatch(relay: $relay, logger: $logger);

            return $publishedMessages === null ? self::FAILURE : self::SUCCESS;
        }

        $this->trap([SIGTERM, SIGINT], function (): void {
            $this->isStopping = true;
        });

        $pollDelay = PollDelay::startingAt(
            minMilliseconds: $settings->minPollMilliseconds,
            maxMilliseconds: $settings->maxPollMilliseconds,
        );

        while (! $this->isStopping) {
            $publishedMessages = $this->relayBatch(relay: $relay, logger: $logger);
            $pollDelay = $publishedMessages === null
                ? $pollDelay->backedOff()
                : $pollDelay->afterBatch(publishedMessages: $publishedMessages);
            Sleep::for($pollDelay->milliseconds)
                ->milliseconds();
        }

        $this->components->info('Outbox relay stopped.');

        return self::SUCCESS;
    }

    /**
     * @return ?int how many messages were published, or null when the batch failed and stays pending
     */
    private function relayBatch(OutboxRelay $relay, LoggerInterface $logger): ?int
    {
        try {
            $publishedMessages = $relay->relayBatch();
        } catch (Throwable $exception) {
            $logger->warning('Outbox relay batch failed; its messages stay pending.', ['exception' => $exception]);
            $failure = $exception->getMessage();
            $this->components->warn(\sprintf('Batch failed, messages stay pending: %s', $failure));

            return null;
        }

        if ($publishedMessages > 0) {
            $this->components->info(\sprintf('Published %d outbox messages.', $publishedMessages));
        }

        return $publishedMessages;
    }
}
