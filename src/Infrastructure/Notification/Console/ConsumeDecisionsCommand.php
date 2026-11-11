<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Console;

use Illuminate\Console\Command;
use Infrastructure\Notification\Messaging\DecisionConsumerSettings;
use Infrastructure\Notification\Messaging\DecisionMessageHandler;
use Infrastructure\Notification\Messaging\DecisionTopology;
use Infrastructure\Shared\Messaging\RabbitMqConnectionFactory;
use Infrastructure\Shared\Observability\Telemetry;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

final class ConsumeDecisionsCommand extends Command
{
    protected $signature = 'notification:consume {--stop-when-empty : Exit once the queue has nothing left}';

    protected $description = 'Notify requesters of the travel request decisions in the notification queue';

    private bool $isStopping = false;

    public function handle(
        RabbitMqConnectionFactory $connections,
        DecisionConsumerSettings $settings,
        DecisionMessageHandler $handler,
        Telemetry $telemetry,
    ): int {
        $connection = $connections->create();
        $channel = $connection->channel();
        DecisionTopology::declare(channel: $channel, settings: $settings);
        $channel->confirm_select();
        $channel->basic_qos(0, $settings->prefetch, false);
        $channel->basic_consume(
            $settings->queue,
            '',
            false,
            false,
            false,
            false,
            static function (AMQPMessage $message) use ($handler): void {
                $handler->handle(message: $message);
            },
        );

        $this->trap([SIGTERM, SIGINT], function (): void {
            $this->isStopping = true;
        });

        $this->components->info(\sprintf('Consuming %s.', $settings->queue));

        while (! $this->isStopping) {
            try {
                $channel->wait(null, false, $settings->waitSeconds);
            } catch (AMQPTimeoutException) {
                if ($this->option('stop-when-empty') === true) {
                    break;
                }
            } finally {
                $telemetry->flush();
            }
        }

        $connection->close();
        $this->components->info('Notification consumer stopped.');

        return self::SUCCESS;
    }
}
