<?php

declare(strict_types=1);

namespace Tests\EndToEnd;

use Illuminate\Support\Facades\Http;
use Tests\Support\LaravelTestCase;

final class OctaneServesHealthTest extends LaravelTestCase
{
    private const string BASE_URL = 'http://127.0.0.1:8000';

    private const int TIMEOUT_SECONDS = 5;

    public function test_RunningOctaneServerAnswersReadiness(): void
    {
        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->acceptJson()
            ->get(self::BASE_URL . '/health/ready');

        self::assertSame(200, $response->status());
        self::assertSame('ok', $response->json('status'));
        self::assertNotSame('', $response->header('X-Request-Id'));
    }

    public function test_OctaneDoesNotLeakRequestIdBetweenRequests(): void
    {
        $clientRequestId = 'first-request-id';

        $firstResponse = Http::timeout(self::TIMEOUT_SECONDS)
            ->withHeaders(['X-Request-Id' => $clientRequestId])
            ->get(self::BASE_URL . '/health/live');
        $secondResponse = Http::timeout(self::TIMEOUT_SECONDS)
            ->get(self::BASE_URL . '/health/live');

        self::assertSame($clientRequestId, $firstResponse->header('X-Request-Id'));
        self::assertNotSame($clientRequestId, $secondResponse->header('X-Request-Id'));
    }
}
