<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Shared\Middleware;

use Illuminate\Http\JsonResponse;
use Illuminate\Log\Context\Repository as Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\LaravelTestCase;

final class AssignRequestContextTest extends LaravelTestCase
{
    private const string CONTEXT_PROBE_URI = '/_test/context';

    private const int UUID_VERSION_7 = 7;

    private const int SHORTEST_ACCEPTED_REQUEST_ID = 8;

    private const int LONGEST_ACCEPTED_REQUEST_ID = 128;

    private const string VALID_TRACEPARENT = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

    private const string TRACE_ID_OF_VALID_TRACEPARENT = '4bf92f3577b34da6a3ce929d0e0e4736';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get(
            self::CONTEXT_PROBE_URI,
            static fn(Context $context): JsonResponse => new JsonResponse([
                'request_id' => $context->get('request_id'),
                'trace_id' => $context->get('trace_id'),
            ]),
        );
    }

    public function test_GeneratesUuidV7RequestIdWhenHeaderIsMissing(): void
    {
        $response = $this->getJson(self::CONTEXT_PROBE_URI);

        $generatedRequestId = $response->headers->get('X-Request-Id');

        self::assertTrue(Str::isUuid($generatedRequestId, self::UUID_VERSION_7));
        $response->assertJsonPath('request_id', $generatedRequestId);
    }

    public function test_KeepsSafeRequestIdFromClient(): void
    {
        $clientRequestId = 'client-req_123.abc';

        $response = $this->getJson(self::CONTEXT_PROBE_URI, ['X-Request-Id' => $clientRequestId]);

        $response->assertHeader('X-Request-Id', $clientRequestId);
        $response->assertJsonPath('request_id', $clientRequestId);
    }

    public function test_ReplacesUnsafeRequestIdFromClient(): void
    {
        $unsafeRequestIds = [
            'tooShort' => str_repeat('a', self::SHORTEST_ACCEPTED_REQUEST_ID - 1),
            'tooLong' => str_repeat('a', self::LONGEST_ACCEPTED_REQUEST_ID + 1),
            'headerInjection' => "evil\nheader",
            'whitespace' => 'has space here',
        ];

        foreach ($unsafeRequestIds as $unsafeRequestId) {
            $response = $this->getJson(self::CONTEXT_PROBE_URI, ['X-Request-Id' => $unsafeRequestId]);

            self::assertNotSame($unsafeRequestId, $response->headers->get('X-Request-Id'));
        }
    }

    public function test_AcceptsRequestIdAtLengthBoundaries(): void
    {
        $boundaryRequestIds = [
            str_repeat('a', self::SHORTEST_ACCEPTED_REQUEST_ID),
            str_repeat('b', self::LONGEST_ACCEPTED_REQUEST_ID),
        ];

        foreach ($boundaryRequestIds as $boundaryRequestId) {
            $response = $this->getJson(self::CONTEXT_PROBE_URI, ['X-Request-Id' => $boundaryRequestId]);

            $response->assertHeader('X-Request-Id', $boundaryRequestId);
        }
    }

    public function test_ExtractsTraceIdFromValidTraceparent(): void
    {
        $response = $this->getJson(self::CONTEXT_PROBE_URI, ['traceparent' => self::VALID_TRACEPARENT]);

        $response->assertJsonPath('trace_id', self::TRACE_ID_OF_VALID_TRACEPARENT);
    }

    public function test_IgnoresInvalidTraceparent(): void
    {
        $invalidTraceparents = [
            'allZeroTraceId' => '00-00000000000000000000000000000000-00f067aa0ba902b7-01',
            'allZeroParentId' => '00-4bf92f3577b34da6a3ce929d0e0e4736-0000000000000000-01',
            'uppercaseHex' => '00-4BF92F3577B34DA6A3CE929D0E0E4736-00f067aa0ba902b7-01',
            'notATraceparent' => 'garbage',
            'leadingGarbage' => 'x' . self::VALID_TRACEPARENT,
            'trailingGarbage' => self::VALID_TRACEPARENT . 'x',
        ];

        foreach ($invalidTraceparents as $invalidTraceparent) {
            $response = $this->getJson(self::CONTEXT_PROBE_URI, ['traceparent' => $invalidTraceparent]);

            $response->assertJsonPath('trace_id', null);
        }
    }
}
