<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\Problem;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * A failure the client caused (a 4xx answer) is expected: it is logged as a warning with what the client was
 * told, without a stack trace, and Laravel's error report is skipped. Anything else is reported as usual.
 */
final readonly class ClientErrorReporter
{
    private const int FIRST_SERVER_ERROR = 500;

    public function __construct(
        private ProblemTypeRegistry $problemTypes,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return bool false when the exception is settled here, so Laravel does not report it again
     */
    public function report(Throwable $exception): bool
    {
        $status = $this->statusOf(exception: $exception);

        if ($status >= self::FIRST_SERVER_ERROR) {
            return true;
        }

        $problemType = $this->problemTypes->findForClass(exceptionClass: $exception::class);
        $details = [
            'status' => $status,
            'type' => $problemType?->type,
            'exception' => $exception::class,
            'reason' => $exception->getMessage(),
        ];
        $this->logger->warning('Request failed with a client error.', $details);

        return false;
    }

    private function statusOf(Throwable $exception): int
    {
        $problemType = $this->problemTypes->findForClass(exceptionClass: $exception::class);

        if ($problemType !== null) {
            return $problemType->status;
        }

        return $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : self::FIRST_SERVER_ERROR;
    }
}
