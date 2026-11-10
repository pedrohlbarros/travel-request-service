<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Shared\Problem;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Support\LaravelTestCase;

final class ProblemDetailsTest extends LaravelTestCase
{
    private const string SECRET_MESSAGE = 'database password is hunter2';

    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/test/explodes', static fn() => throw new RuntimeException(self::SECRET_MESSAGE));
    }

    public function test_UnknownRouteIsNotFound(): void
    {
        $response = $this->getJson('/api/v1/nowhere');

        $response->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson(['type' => '/problems/not-found', 'status' => 404, 'instance' => '/api/v1/nowhere']);
    }

    public function test_OtherHttpErrorsUseAboutBlankWithTheStatusTitle(): void
    {
        $this->getJson('/api/v1/auth/login')
            ->assertMethodNotAllowed()
            ->assertHeader('Allow')
            ->assertJson(['type' => 'about:blank', 'title' => 'Method Not Allowed', 'status' => 405]);
    }

    public function test_UnexpectedErrorsHideTheirDetails(): void
    {
        $response = $this->getJson('/test/explodes');

        $response->assertInternalServerError()
            ->assertJsonPath('type', '/problems/internal-error')
            ->assertDontSee(self::SECRET_MESSAGE);
    }

    public function test_EveryProblemCarriesTheRequestAndTraceIds(): void
    {
        $response = $this->getJson(
            '/api/v1/nowhere',
            ['traceparent' => '00-' . self::TRACE_ID . '-00f067aa0ba902b7-01'],
        );

        $response->assertJsonPath('request_id', $response->headers->get('X-Request-Id'))
            ->assertJsonPath('trace_id', self::TRACE_ID);
    }
}
