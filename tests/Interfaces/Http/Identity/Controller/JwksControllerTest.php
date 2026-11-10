<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Identity\Controller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Identity\AuthenticatesUsers;
use Tests\Support\Identity\JwtSegments;
use Tests\Support\LaravelTestCase;

final class JwksControllerTest extends LaravelTestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_PublishesTheKeyThatSignsAccessTokens(): void
    {
        $this->createUser(email: 'robin@example.com');
        $accessToken = $this->loginAs(email: 'robin@example.com')->accessToken;
        $header = JwtSegments::header(token: $accessToken);

        $response = $this->getJson('/.well-known/jwks.json');

        $response->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertJsonPath('keys.0.kid', $header['kid'])
            ->assertJsonPath('keys.0.kty', 'RSA')
            ->assertJsonPath('keys.0.alg', 'RS256')
            ->assertJsonPath('keys.0.use', 'sig')
            ->assertJsonMissingPath('keys.0.d');
    }
}
