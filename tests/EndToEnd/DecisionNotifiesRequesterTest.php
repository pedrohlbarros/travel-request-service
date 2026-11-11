<?php

declare(strict_types=1);

namespace Tests\EndToEnd;

use DateTimeImmutable;
use Dotenv\Dotenv;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\Support\LaravelTestCase;

/**
 * The whole path through the running services: API (Octane) → outbox → relay → RabbitMQ → consumer →
 * Mailpit and the notifications API. It works on the development database, with users it creates itself.
 */
final class DecisionNotifiesRequesterTest extends LaravelTestCase
{
    private const string API = 'http://127.0.0.1:8000/api/v1';

    private const string MAILPIT_SEARCH = 'http://mailpit:8025/api/v1/search';

    private const string PASSWORD = 'e2e-password-123';

    private const string API_DATE = 'Y-m-d';

    private const string MESSAGE_DATE = 'd/m/Y';

    private const int TIMEOUT_SECONDS = 5;

    private const int WAIT_SECONDS = 20;

    private const int POLL_MICROSECONDS = 250_000;

    public function test_ApprovingARequestMailsAndNotifiesTheRequester(): void
    {
        $runId = bin2hex(random_bytes(4));
        $requesterEmail = "e2e-requester-{$runId}@example.com";
        $adminEmail = "e2e-admin-{$runId}@example.com";
        $registration = ['name' => 'E2E Requester', 'email' => $requesterEmail, 'password' => self::PASSWORD];
        $this->api()
            ->post('/auth/register', $registration)
            ->throw();
        $this->createAdmin(email: $adminEmail);
        $requesterToken = $this->accessTokenOf(email: $requesterEmail);
        $adminToken = $this->accessTokenOf(email: $adminEmail);
        $departure = new DateTimeImmutable('+30 days');
        $return = $departure->modify('+5 days');
        $trip = [
            'destination' => 'Wano',
            'departure_date' => $departure->format(self::API_DATE),
            'return_date' => $return->format(self::API_DATE),
        ];
        $travelRequestId = $this->api(token: $requesterToken)
            ->post('/travel-requests', $trip)
            ->throw()
            ->json('data.id');
        \assert(\is_string($travelRequestId));

        $approval = $this->api(token: $adminToken)
            ->withHeaders(['If-Match' => '"1"'])
            ->post("/travel-requests/{$travelRequestId}/approve");

        $notifications = $this->eventually(probe: fn(): ?Response => $this->notificationsOf(token: $requesterToken));
        $expectedMessage = \sprintf(
            'Seu pedido de viagem para Wano, de %s a %s, foi aprovado.',
            $departure->format(self::MESSAGE_DATE),
            $return->format(self::MESSAGE_DATE),
        );
        $mail = $this->eventually(probe: fn(): ?array => $this->mailTo(email: $requesterEmail));

        self::assertSame(200, $approval->status());
        self::assertSame('travel_request.approved', $notifications->json('data.0.type'));
        self::assertSame($travelRequestId, $notifications->json('data.0.data.travel_request_id'));
        self::assertSame($expectedMessage, $notifications->json('data.0.message'));
        self::assertSame('Seu pedido de viagem para Wano foi aprovado', $mail['Subject'] ?? null);
    }

    private function api(?string $token = null): PendingRequest
    {
        $request = Http::baseUrl(self::API)
            ->timeout(self::TIMEOUT_SECONDS)
            ->acceptJson();

        return $token === null ? $request : $request->withToken($token);
    }

    private function accessTokenOf(string $email): string
    {
        $accessToken = $this->api()
            ->post('/auth/login', ['email' => $email, 'password' => self::PASSWORD])
            ->throw()
            ->json('access_token');
        \assert(\is_string($accessToken));

        return $accessToken;
    }

    /**
     * Admins only come from the console; it runs against the development database the server uses.
     */
    private function createAdmin(string $email): void
    {
        $dotenv = (string) file_get_contents(base_path('.env'));
        $environment = Dotenv::parse($dotenv);

        Process::env(['DB_DATABASE' => $environment['DB_DATABASE'] ?? ''])
            ->run(['php', 'artisan', 'identity:create-admin', '--name=E2E Admin', "--email={$email}", '--password=' . self::PASSWORD])
            ->throw();
    }

    private function notificationsOf(string $token): ?Response
    {
        $response = $this->api(token: $token)
            ->get('/notifications');
        $notifications = $response->json('data');

        return \is_array($notifications) && $notifications !== [] ? $response : null;
    }

    /**
     * @return ?array<mixed>
     */
    private function mailTo(string $email): ?array
    {
        $messages = Http::timeout(self::TIMEOUT_SECONDS)
            ->get(self::MAILPIT_SEARCH, ['query' => "to:\"{$email}\""])
            ->json('messages');

        return \is_array($messages) && \is_array($messages[0] ?? null) ? $messages[0] : null;
    }

    /**
     * @template T
     *
     * @param callable(): ?T $probe
     *
     * @return T
     */
    private function eventually(callable $probe): mixed
    {
        $deadline = microtime(true) + self::WAIT_SECONDS;

        do {
            $result = $probe();

            if ($result !== null) {
                return $result;
            }

            usleep(self::POLL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        self::fail(\sprintf('Nothing arrived within %d seconds.', self::WAIT_SECONDS));
    }
}
