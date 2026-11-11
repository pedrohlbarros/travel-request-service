<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Notification\Delivery;

use DateTimeImmutable;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Transport\ArrayTransport;
use Infrastructure\Notification\Delivery\DecisionTextsFactory;
use Infrastructure\Notification\Delivery\LaravelDecisionNotifier;
use Infrastructure\Notification\Delivery\ReverbDecisionBroadcaster;
use Infrastructure\Notification\Persistence\NotificationRows;
use Infrastructure\Shared\Persistence\BinaryUuid;
use Psr\Log\LogLevel;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\Support\LaravelTestCase;
use Tests\Support\Notification\Broadcasting\RecordingBroadcaster;
use Tests\Support\Notification\Fixture\DecisionMother as Mother;
use Tests\Support\Shared\Log\RecordingLogger;
use Tests\Support\Shared\Port\FrozenClock;

final class DecisionDeliveryTest extends LaravelTestCase
{
    use RefreshDatabase;

    private const string LOCALE = 'pt_BR';

    private const string NOW = '2026-11-10T09:31:00+00:00';

    private const string APPROVED_MESSAGE = 'Seu pedido de viagem para Wano, de 01/12/2026 a 05/12/2026, foi aprovado.';

    public function test_TextsAreInPortugueseForEachOutcome(): void
    {
        $texts = $this->texts();

        $approved = $texts->for(decision: Mother::decision());
        $cancelled = $texts->for(decision: Mother::decision(outcome: 'cancelled'));

        self::assertSame(self::LOCALE, $approved->locale);
        self::assertSame('Seu pedido de viagem para Wano foi aprovado', $approved->subject);
        self::assertSame('Olá, Monkey D. Luffy!', $approved->greeting);
        self::assertSame(self::APPROVED_MESSAGE, $approved->message);
        self::assertSame('Equipe de Viagens', $approved->salutation);
        self::assertSame('Seu pedido de viagem para Wano foi cancelado', $cancelled->subject);
        self::assertSame(
            'Seu pedido de viagem para Wano, de 01/12/2026 a 05/12/2026, foi cancelado.',
            $cancelled->message,
        );
    }

    public function test_TheNotifierStoresTheNotificationAndMailsTheRequester(): void
    {
        $this->app->make(Config::class)
            ->set('app.name', 'Travel Requests');

        $this->app->make(LaravelDecisionNotifier::class)
            ->notify(decision: Mother::decision());

        $row = $this->app->make(ConnectionInterface::class)
            ->table(NotificationRows::TABLE)
            ->first();
        $email = $this->sentEmails()[0] ?? null;

        self::assertNotNull($row);
        self::assertIsString($row->id);
        self::assertIsString($row->notifiable_id);
        self::assertIsString($row->data);
        self::assertSame(Mother::EVENT_ID, BinaryUuid::fromBytes(bytes: $row->id));
        self::assertSame(Mother::REQUESTER_ID, BinaryUuid::fromBytes(bytes: $row->notifiable_id));
        self::assertSame('travel_request.approved', $row->type);
        self::assertSame(self::APPROVED_MESSAGE, $row->message);
        self::assertNull($row->read_at);
        self::assertSame(
            [
                'travel_request_id' => Mother::TRAVEL_REQUEST_ID,
                'destination' => Mother::DESTINATION,
                'departure_date' => Mother::DEPARTURE_DATE,
                'return_date' => Mother::RETURN_DATE,
                'status' => 'approved',
                'decided_by' => Mother::ADMIN_ID,
            ],
            json_decode($row->data, true),
        );
        self::assertNotNull($email);
        self::assertSame('Seu pedido de viagem para Wano foi aprovado', $email->getSubject());
        self::assertSame(Mother::REQUESTER_EMAIL, $email->getTo()[0]->getAddress());
        self::assertSame(Mother::REQUESTER_NAME, $email->getTo()[0]->getName());
        self::assertStringContainsString('Olá, Monkey D. Luffy!', (string) $email->getHtmlBody());
        self::assertStringContainsString('Todos os direitos reservados.', (string) $email->getHtmlBody());
    }

    public function test_TheBroadcastGoesToTheRequestersPrivateChannel(): void
    {
        $broadcaster = new RecordingBroadcaster();

        $this->broadcasterSendingThrough(broadcaster: $broadcaster, logger: new RecordingLogger())
            ->broadcast(decision: Mother::decision());

        $broadcast = $broadcaster->broadcasts[0] ?? null;

        self::assertNotNull($broadcast);
        self::assertSame(['private-users.' . Mother::REQUESTER_ID], $broadcast['channels']);
        self::assertSame(ReverbDecisionBroadcaster::EVENT, $broadcast['event']);
        self::assertSame(Mother::EVENT_ID, $broadcast['payload']['id']);
        self::assertSame('travel_request.approved', $broadcast['payload']['type']);
        self::assertSame(self::APPROVED_MESSAGE, $broadcast['payload']['message']);
        self::assertSame(self::NOW, $broadcast['payload']['created_at']);
        self::assertIsArray($broadcast['payload']['data']);
        self::assertSame(Mother::TRAVEL_REQUEST_ID, $broadcast['payload']['data']['travel_request_id']);
    }

    public function test_AFailedBroadcastIsLoggedAndSwallowed(): void
    {
        $logger = new RecordingLogger();

        $this->broadcasterSendingThrough(broadcaster: new RecordingBroadcaster(isFailing: true), logger: $logger)
            ->broadcast(decision: Mother::decision());

        $record = $logger->records[0] ?? null;

        self::assertNotNull($record);
        self::assertSame(LogLevel::WARNING, $record['level']);
        self::assertSame('Broadcast of a decision failed; the stored notification stays readable.', $record['message']);
        self::assertSame(Mother::EVENT_ID, $record['context']['event_id']);
    }

    private function texts(): DecisionTextsFactory
    {
        return new DecisionTextsFactory(translator: $this->app->make(Translator::class), locale: self::LOCALE);
    }

    private function broadcasterSendingThrough(
        RecordingBroadcaster $broadcaster,
        RecordingLogger $logger,
    ): ReverbDecisionBroadcaster {
        $broadcasting = $this->app->make(BroadcastManager::class);
        $broadcasting->extend('recording', fn(): RecordingBroadcaster => $broadcaster);
        $this->app->make(Config::class)
            ->set('broadcasting.connections.recording', ['driver' => 'recording']);
        $this->app->make(Config::class)
            ->set('broadcasting.default', 'recording');
        $now = new DateTimeImmutable(self::NOW);

        return new ReverbDecisionBroadcaster(
            broadcasting: $broadcasting,
            texts: $this->texts(),
            clock: new FrozenClock(frozenAt: $now),
            logger: $logger,
        );
    }

    /**
     * @return list<Email>
     */
    private function sentEmails(): array
    {
        $mailer = $this->app->make(MailManager::class)
            ->mailer('array');
        \assert($mailer instanceof Mailer);
        $transport = $mailer->getSymfonyTransport();
        \assert($transport instanceof ArrayTransport);
        $emails = [];

        foreach ($transport->messages() as $sentMessage) {
            \assert($sentMessage instanceof SentMessage);
            $email = $sentMessage->getOriginalMessage();
            \assert($email instanceof Email);
            $emails[] = $email;
        }

        return $emails;
    }
}
