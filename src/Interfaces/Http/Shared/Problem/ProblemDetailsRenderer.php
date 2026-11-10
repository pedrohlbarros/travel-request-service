<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\Problem;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Log\Context\Repository as Context;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final readonly class ProblemDetailsRenderer
{
    public const string CONTENT_TYPE = 'application/problem+json';

    public const string BLANK_TYPE = 'about:blank';

    public const string VALIDATION_FAILED_TYPE = '/problems/validation-failed';

    public const string INTERNAL_ERROR_TYPE = '/problems/internal-error';

    public function __construct(
        private ProblemTypeRegistry $problemTypes,
        private Context $context,
    ) {}

    public function render(Throwable $exception, Request $request): JsonResponse
    {
        if ($exception instanceof ValidationException) {
            return $this->respond(
                request: $request,
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                type: self::VALIDATION_FAILED_TYPE,
                title: 'The request is invalid.',
                detail: $exception->getMessage(),
                extensions: ['errors' => $exception->errors()],
            );
        }

        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $problemType = $this->problemTypes->find(exception: $exception);

        if ($problemType !== null) {
            $detail = $problemType->isDetailFromException ? $exception->getMessage() : $problemType->title;

            return $this->respond(
                request: $request,
                status: $problemType->status,
                type: $problemType->type,
                title: $problemType->title,
                detail: $detail,
                extensions: [],
                headers: [...$headers, ...$problemType->headers],
            );
        }

        if ($exception instanceof HttpExceptionInterface) {
            $statusTitle = Response::$statusTexts[$exception->getStatusCode()] ?? 'Error';

            return $this->respond(
                request: $request,
                status: $exception->getStatusCode(),
                type: self::BLANK_TYPE,
                title: $statusTitle,
                detail: $statusTitle,
                extensions: [],
                headers: $headers,
            );
        }

        return $this->respond(
            request: $request,
            status: Response::HTTP_INTERNAL_SERVER_ERROR,
            type: self::INTERNAL_ERROR_TYPE,
            title: 'Something went wrong.',
            detail: 'The error was logged; use request_id to find it.',
        );
    }

    /**
     * @param array<string, mixed> $extensions
     * @param array<mixed> $headers
     */
    private function respond(
        Request $request,
        int $status,
        string $type,
        string $title,
        string $detail,
        array $extensions = [],
        array $headers = [],
    ): JsonResponse {
        $problem = [
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => '/' . ltrim($request->path(), '/'),
            ...$extensions,
            'request_id' => $this->context->get('request_id'),
            'trace_id' => $this->context->get('trace_id'),
        ];

        return new JsonResponse($problem, $status, [...$headers, 'Content-Type' => self::CONTENT_TYPE]);
    }
}
