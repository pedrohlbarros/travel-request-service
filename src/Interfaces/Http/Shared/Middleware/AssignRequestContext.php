<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Log\Context\Repository as Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final readonly class AssignRequestContext
{
    public const string REQUEST_ID_HEADER = 'X-Request-Id';

    public const string TRACEPARENT_HEADER = 'traceparent';

    /** Client-provided ids are only trusted when they cannot break headers or logs. */
    private const string SAFE_CLIENT_REQUEST_ID = '/\A[A-Za-z0-9._-]{8,128}\z/';

    /** W3C Trace Context; all-zero trace and parent ids are invalid by the spec. */
    private const string W3C_TRACEPARENT = '/\A(?<version>[0-9a-f]{2})-(?<trace_id>(?!0{32})[0-9a-f]{32})-(?<parent_id>(?!0{16})[0-9a-f]{16})-(?<flags>[0-9a-f]{2})\z/';

    public function __construct(
        private Context $context,
    ) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestIdFor(request: $request);
        $this->context->add('request_id', $requestId);

        $traceId = $this->traceIdFrom(request: $request);
        if ($traceId !== null) {
            $this->context->add('trace_id', $traceId);
            $this->context->addHidden('traceparent', $request->headers->get(self::TRACEPARENT_HEADER));
        }

        $response = $next($request);
        $response->headers->set(self::REQUEST_ID_HEADER, $requestId);

        return $response;
    }

    private function requestIdFor(Request $request): string
    {
        $clientRequestId = $request->headers->get(self::REQUEST_ID_HEADER);

        if ($clientRequestId !== null && preg_match(self::SAFE_CLIENT_REQUEST_ID, $clientRequestId) === 1) {
            return $clientRequestId;
        }

        return Str::uuid7()
            ->toString();
    }

    private function traceIdFrom(Request $request): ?string
    {
        $traceparent = $request->headers->get(self::TRACEPARENT_HEADER);

        if ($traceparent === null || preg_match(self::W3C_TRACEPARENT, $traceparent, $traceparentParts) !== 1) {
            return null;
        }

        return $traceparentParts['trace_id'];
    }
}
