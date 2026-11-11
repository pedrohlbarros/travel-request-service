<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Shared\ApiDocs;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\LaravelTestCase;

final class ApiDocsTest extends LaravelTestCase
{
    private const string OPENAPI = '/docs/api.json';

    private const string APPROVE = 'paths./travel-requests/{travelRequestId}/approve.post';

    private const string PROBLEM_SCHEMA = 'content.application/problem+json.schema';

    public function test_DocsAreClosedOutsideLocal(): void
    {
        $response = $this->get(self::OPENAPI);

        $response->assertForbidden();
    }

    public function test_DocumentsEveryTravelRequestRoute(): void
    {
        $openApi = $this->openApiInLocal();

        $openApi->assertOk()
            ->assertJsonPath('servers.0.url', url('/api/v1'))
            ->assertJsonStructure([
                'paths' => [
                    '/travel-requests' => ['get', 'post'],
                    '/travel-requests/{travelRequestId}' => ['get', 'patch'],
                    '/travel-requests/{travelRequestId}/approve' => ['post'],
                    '/travel-requests/{travelRequestId}/cancel' => ['post'],
                ],
            ]);
    }

    public function test_ErrorsAreProblemDetailsFromTheRegistry(): void
    {
        $openApi = $this->openApiInLocal();

        $versionMismatch = 'components.responses.TravelRequestVersionMismatch.' . self::PROBLEM_SCHEMA;
        $versionMismatchReference = '#/components/responses/TravelRequestVersionMismatch';

        $openApi->assertJsonPath(self::APPROVE . '.responses.412.$ref', $versionMismatchReference)
            ->assertJsonPath($versionMismatch . '.properties.type.const', '/problems/version-mismatch')
            ->assertJsonPath($versionMismatch . '.properties.status.const', 412)
            ->assertJsonMissingPath($versionMismatch . '.properties.errors');
    }

    public function test_RequestValidationErrorsListTheFields(): void
    {
        $openApi = $this->openApiInLocal();

        $validationFailed = 'components.responses.ValidationException.' . self::PROBLEM_SCHEMA;

        $openApi->assertJsonPath($validationFailed . '.properties.type.const', '/problems/validation-failed')
            ->assertJsonPath($validationFailed . '.properties.errors.type', 'object');
    }

    public function test_ConditionalWritesDocumentIfMatchAnd428AndETag(): void
    {
        $openApi = $this->openApiInLocal();

        $preconditionRequired = self::APPROVE . '.responses.428.' . self::PROBLEM_SCHEMA;

        $openApi->assertJsonPath(self::APPROVE . '.parameters.1.name', 'If-Match')
            ->assertJsonPath(self::APPROVE . '.parameters.1.required', true)
            ->assertJsonPath($preconditionRequired . '.properties.type.const', '/problems/precondition-required')
            ->assertJsonPath(self::APPROVE . '.responses.200.headers.ETag.schema.type', 'string');
    }

    public function test_OnlyAuthenticatedRoutesRequireTheBearerToken(): void
    {
        $openApi = $this->openApiInLocal();

        $openApi->assertJsonPath('components.securitySchemes.http.scheme', 'bearer')
            ->assertJsonPath('paths./auth/login.post.security', [])
            ->assertJsonMissingPath('paths./travel-requests.get.security');
    }

    /**
     * @return TestResponse<Response>
     */
    private function openApiInLocal(): TestResponse
    {
        $this->app->detectEnvironment(static fn(): string => 'local');

        return $this->getJson(self::OPENAPI);
    }
}
