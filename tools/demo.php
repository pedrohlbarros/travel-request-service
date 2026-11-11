<?php

declare(strict_types=1);

/**
 * Walks the whole flow against the running stack: Luffy asks for a trip, the admin approves it, and the
 * notification lands in Luffy's inbox and in Mailpit. Runs inside the app container through `make demo`.
 */
final class Demo
{
    private const string API = 'http://127.0.0.1:8000/api/v1';

    private const string MAILPIT_API = 'http://mailpit:8025/api/v1/messages';

    private const string REQUESTER_EMAIL = 'luffy@example.com';

    private const string ADMIN_EMAIL = 'admin@example.com';

    private const string DEMO_PASSWORD = 'password123';

    private const int WAIT_SECONDS = 15;

    private const int POLL_MICROSECONDS = 250_000;

    private const int DAYS_UNTIL_DEPARTURE = 30;

    private const int TRIP_DAYS = 5;

    private const int HTTP_OK = 200;

    private const int HTTP_CREATED = 201;

    public function run(string $appUrl, string $mailpitUrl): int
    {
        self::title(text: 'Demo: do pedido à notificação');

        $requesterToken = self::login(email: self::REQUESTER_EMAIL);
        $adminToken = self::login(email: self::ADMIN_EMAIL);
        $travelRequest = self::create(token: $requesterToken);
        $travelRequestId = self::stringAt(values: $travelRequest, key: 'id');
        $decision = self::approve(token: $adminToken, travelRequestId: $travelRequestId);
        $notification = self::waitForNotification(token: $requesterToken, travelRequestId: $travelRequestId);

        if ($notification === null) {
            self::line(text: '✗ A notificação não chegou em ' . self::WAIT_SECONDS . ' s. Veja make consumer-logs e make dlq.');

            return 1;
        }

        self::step(number: 5, text: 'Notificação entregue ao Luffy');
        self::line(text: '  ' . self::stringAt(values: $notification, key: 'message'));
        self::showEmail(travelRequestId: $travelRequestId);

        self::title(text: 'Pronto');
        $status = self::stringAt(values: $decision, key: 'status');
        self::line(text: '  Pedido:        ' . $travelRequestId . ' (' . $status . ')');
        self::line(text: '  Ao vivo:       ' . $appUrl . '/docs/notifications (entre como Luffy e rode make demo de novo)');
        self::line(text: '  E-mail:        ' . $mailpitUrl);

        return 0;
    }

    private static function login(string $email): string
    {
        $credentials = ['email' => $email, 'password' => self::DEMO_PASSWORD];
        $response = self::call(method: 'POST', path: '/auth/login', body: $credentials);
        self::expect(response: $response, status: self::HTTP_OK, action: 'login de ' . $email);
        $number = $email === self::REQUESTER_EMAIL ? 1 : 2;
        self::step(number: $number, text: 'Login de ' . $email . ' → 200, access token RS256 de 15 min');

        return self::stringAt(values: $response['body'], key: 'access_token');
    }

    /**
     * @return array<string, mixed>
     */
    private static function create(string $token): array
    {
        $departure = new DateTimeImmutable('today')->modify('+' . self::DAYS_UNTIL_DEPARTURE . ' days');
        $return = $departure->modify('+' . self::TRIP_DAYS . ' days');
        $trip = [
            'destination' => 'Wano',
            'departure_date' => $departure->format('Y-m-d'),
            'return_date' => $return->format('Y-m-d'),
        ];
        $response = self::call(
            method: 'POST',
            path: '/travel-requests',
            body: $trip,
            token: $token,
        );
        self::expect(response: $response, status: self::HTTP_CREATED, action: 'criação do pedido');
        $travelRequest = self::arrayAt(values: $response['body'], key: 'data');
        self::step(
            number: 3,
            text: 'Luffy pede uma viagem para Wano → 201, status requested, ETag ' . $response['etag'],
        );

        return $travelRequest;
    }

    /**
     * @return array<string, mixed>
     */
    private static function approve(string $token, string $travelRequestId): array
    {
        $response = self::call(
            method: 'POST',
            path: '/travel-requests/' . $travelRequestId . '/approve',
            token: $token,
            ifMatch: '"1"',
        );
        self::expect(response: $response, status: self::HTTP_OK, action: 'aprovação');
        self::step(number: 4, text: 'Admin aprova com If-Match: "1" → 200, versão 2; o evento vai para o outbox');

        return self::arrayAt(values: $response['body'], key: 'data');
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function waitForNotification(string $token, string $travelRequestId): ?array
    {
        $startedAt = microtime(true);

        while (microtime(true) - $startedAt < self::WAIT_SECONDS) {
            $response = self::call(method: 'GET', path: '/notifications?per_page=20', token: $token);

            foreach (self::arrayAt(values: $response['body'], key: 'data') as $notification) {
                \assert(\is_array($notification));
                $details = $notification['data'] ?? [];

                if (\is_array($details) && ($details['travel_request_id'] ?? null) === $travelRequestId) {
                    $elapsedMilliseconds = (int) round((microtime(true) - $startedAt) * 1000);
                    self::line(text: '  … relay → RabbitMQ → consumidor em ' . $elapsedMilliseconds . ' ms');

                    /** @var array<string, mixed> $notification */
                    return $notification;
                }
            }

            usleep(self::POLL_MICROSECONDS);
        }

        return null;
    }

    private static function showEmail(string $travelRequestId): void
    {
        $messages = @file_get_contents(self::MAILPIT_API . '?limit=5');

        if ($messages === false) {
            return;
        }

        $decoded = json_decode($messages, true);
        $list = \is_array($decoded) && \is_array($decoded['messages'] ?? null) ? $decoded['messages'] : [];

        foreach ($list as $message) {
            if (\is_array($message) && \is_string($message['Subject'] ?? null)) {
                self::step(number: 6, text: 'E-mail no Mailpit: "' . $message['Subject'] . '"');

                return;
            }
        }

        self::line(text: '  (nenhum e-mail no Mailpit para ' . $travelRequestId . ')');
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{status: int, body: array<string, mixed>, etag: string}
     */
    private static function call(
        string $method,
        string $path,
        array $body = [],
        ?string $token = null,
        ?string $ifMatch = null,
    ): array {
        $headers = ['Accept: application/json', 'Content-Type: application/json'];

        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        if ($ifMatch !== null) {
            $headers[] = 'If-Match: ' . $ifMatch;
        }

        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $method === 'GET' ? '' : (string) json_encode($body),
            'ignore_errors' => true,
        ]]);
        $responseBody = file_get_contents(self::API . $path, false, $context);
        $responseHeaders = http_get_last_response_headers() ?? [];
        $decoded = json_decode((string) $responseBody, true);

        /** @var array<string, mixed> $decodedBody */
        $decodedBody = \is_array($decoded) ? $decoded : [];

        return [
            'status' => self::statusOf(headers: $responseHeaders),
            'body' => $decodedBody,
            'etag' => self::headerOf(headers: $responseHeaders, name: 'etag'),
        ];
    }

    /**
     * @param list<string> $headers
     */
    private static function statusOf(array $headers): int
    {
        $statusLine = $headers[0] ?? '';
        $parts = explode(' ', $statusLine);

        return (int) ($parts[1] ?? 0);
    }

    /**
     * @param list<string> $headers
     */
    private static function headerOf(array $headers, string $name): string
    {
        foreach ($headers as $header) {
            $parts = explode(':', $header, 2);

            if (strtolower(trim($parts[0])) === $name && isset($parts[1])) {
                return trim($parts[1]);
            }
        }

        return '';
    }

    /**
     * @param array{status: int, body: array<string, mixed>, etag: string} $response
     */
    private static function expect(array $response, int $status, string $action): void
    {
        if ($response['status'] === $status) {
            return;
        }

        $problem = self::stringAt(values: $response['body'], key: 'detail');
        fwrite(STDERR, '✗ Falhou a ' . $action . ': HTTP ' . $response['status'] . ' ' . $problem . PHP_EOL);
        fwrite(STDERR, '  O app está no ar (make up) e as contas de demo existem (make seed)?' . PHP_EOL);

        exit(1);
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function stringAt(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private static function arrayAt(array $values, string $key): array
    {
        $value = $values[$key] ?? [];

        /** @var array<string, mixed> */
        return \is_array($value) ? $value : [];
    }

    private static function title(string $text): void
    {
        self::line(text: PHP_EOL . '▶ ' . $text);
    }

    private static function step(int $number, string $text): void
    {
        self::line(text: '  ' . $number . '. ' . $text);
    }

    private static function line(string $text): void
    {
        fwrite(STDOUT, $text . PHP_EOL);
    }
}

$appUrl = getenv('DEMO_APP_URL');
$mailpitUrl = getenv('DEMO_MAILPIT_URL');

exit(new Demo()->run(
    appUrl: \is_string($appUrl) ? $appUrl : 'http://localhost:8000',
    mailpitUrl: \is_string($mailpitUrl) ? $mailpitUrl : 'http://localhost:8025',
));
