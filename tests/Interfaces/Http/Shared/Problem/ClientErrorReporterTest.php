<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Shared\Problem;

use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\Exception\InvalidStatusTransition;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Interfaces\Http\Shared\Problem\ClientErrorReporter;
use Interfaces\Http\Shared\Problem\ProblemTypeRegistry;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\Support\LaravelTestCase;
use Tests\Support\Shared\Log\RecordingLogger;

final class ClientErrorReporterTest extends LaravelTestCase
{
    private const string TRAVEL_REQUEST_ID = '019a6a10-aaaa-7bbb-8ccc-000000000001';

    private RecordingLogger $logger;

    private ClientErrorReporter $reporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new RecordingLogger();
        $this->reporter = new ClientErrorReporter(
            problemTypes: $this->app->make(ProblemTypeRegistry::class),
            logger: $this->logger,
        );
    }

    public function test_AClientErrorIsAWarningWithoutTheStackTrace(): void
    {
        $conflict = InvalidStatusTransition::between(
            id: TravelRequestId::fromString(id: self::TRAVEL_REQUEST_ID),
            currentStatus: TravelRequestStatus::Approved,
            targetStatus: TravelRequestStatus::Cancelled,
        );

        $isReportedAsUsual = $this->reporter->report(exception: $conflict);

        $record = $this->logger->records[0] ?? null;

        self::assertFalse($isReportedAsUsual);
        self::assertNotNull($record);
        self::assertSame(LogLevel::WARNING, $record['level']);
        self::assertSame('Request failed with a client error.', $record['message']);
        self::assertSame(
            [
                'status' => 409,
                'type' => '/problems/invalid-status-transition',
                'exception' => InvalidStatusTransition::class,
                'reason' => $conflict->getMessage(),
            ],
            $record['context'],
        );
    }

    public function test_AnHttpClientErrorOutsideTheRegistryIsAWarningToo(): void
    {
        $isReportedAsUsual = $this->reporter->report(exception: new MethodNotAllowedHttpException(['GET']));

        self::assertFalse($isReportedAsUsual);
        self::assertSame(405, $this->logger->records[0]['context']['status'] ?? null);
        self::assertNull($this->logger->records[0]['context']['type'] ?? null);
    }

    public function test_ServerErrorsAreReportedAsUsual(): void
    {
        $isUnexpectedReported = $this->reporter->report(exception: new RuntimeException('The database is gone.'));
        $isUnavailableReported = $this->reporter->report(exception: new ServiceUnavailableHttpException());

        self::assertTrue($isUnexpectedReported);
        self::assertTrue($isUnavailableReported);
        self::assertSame([], $this->logger->records);
    }
}
