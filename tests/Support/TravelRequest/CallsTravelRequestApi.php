<?php

declare(strict_types=1);

namespace Tests\Support\TravelRequest;

use Application\Identity\Port\AccessTokenIssuer;
use Application\Shared\Port\IdentifierGenerator;
use DateTimeImmutable;
use DateTimeZone;
use Domain\Identity\Entity\User;
use Domain\Identity\Enum\Role;
use Domain\Identity\Repository\UserRepository;
use Domain\Identity\ValueObject\Email;
use Domain\Identity\ValueObject\PasswordHash;
use Domain\Identity\ValueObject\UserId;
use Domain\Identity\ValueObject\UserName;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Identity\AuthenticatesUsers;

/**
 * For HTTP tests of travel requests: a requester, another user and an admin, all signed in.
 */
trait CallsTravelRequestApi
{
    use AuthenticatesUsers;

    protected const string TRAVEL_REQUESTS = '/api/v1/travel-requests';

    protected const string REQUESTER_EMAIL = 'luffy@example.com';

    protected const string OTHER_USER_EMAIL = 'zoro@example.com';

    protected const string ADMIN_EMAIL = 'admin@example.com';

    protected const string REQUESTER_NAME = 'Monkey D. Luffy';

    protected const string ADMIN_NAME = 'Nico Robin';

    protected string $requesterId;

    protected string $adminId;

    protected string $requesterToken;

    protected string $otherUserToken;

    protected string $adminToken;

    private string $lastAccessToken = '';

    /**
     * Skips password hashing: these tests are about travel requests, and each Argon2id hash costs a lot of time.
     */
    protected function signInEveryone(): void
    {
        $this->requesterId = $this->signIn(email: self::REQUESTER_EMAIL, name: self::REQUESTER_NAME, role: Role::User);
        $this->requesterToken = $this->lastAccessToken;
        $this->signIn(email: self::OTHER_USER_EMAIL, name: 'Roronoa Zoro', role: Role::User);
        $this->otherUserToken = $this->lastAccessToken;
        $this->adminId = $this->signIn(email: self::ADMIN_EMAIL, name: self::ADMIN_NAME, role: Role::Admin);
        $this->adminToken = $this->lastAccessToken;
    }

    protected static function daysFromToday(int $days): string
    {
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));

        return $today->modify(\sprintf('%+d days', $days))
            ->format('Y-m-d');
    }

    /**
     * @param array<string, string> $details overrides of the default trip
     *
     * @return array<string, mixed> the created request, as the API returns it
     */
    protected function createTravelRequestAs(string $token, array $details = []): array
    {
        $trip = [
            'destination' => 'Lisbon',
            'departure_date' => self::daysFromToday(days: 10),
            'return_date' => self::daysFromToday(days: 15),
            ...$details,
        ];

        $response = $this->postJson(self::TRAVEL_REQUESTS, $trip, $this->bearer(accessToken: $token));
        $response->assertCreated();
        $created = $response->json('data');
        \assert(\is_array($created));

        /** @var array<string, mixed> $created */
        return $created;
    }

    /**
     * @param array<string, mixed> $travelRequest
     */
    protected function idOf(array $travelRequest): string
    {
        $id = $travelRequest['id'] ?? null;
        \assert(\is_string($id));

        return $id;
    }

    /**
     * @return array<string, string>
     */
    protected function bearerWithIfMatch(string $token, string $ifMatch): array
    {
        return [...$this->bearer(accessToken: $token), 'If-Match' => $ifMatch];
    }

    private function signIn(string $email, string $name, Role $role): string
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $newId = $this->app->make(IdentifierGenerator::class)
            ->uuid7();
        $userId = UserId::fromString(id: $newId);
        $user = User::register(
            id: $userId,
            name: UserName::fromString(name: $name),
            email: Email::fromString(address: $email),
            passwordHash: new PasswordHash(value: 'not-a-real-hash'),
            role: $role,
            registeredAt: $now,
        );
        $this->app->make(UserRepository::class)
            ->add(user: $user);
        $this->lastAccessToken = $this->app->make(AccessTokenIssuer::class)
            ->issue(userId: $userId, issuedAt: $now, expiresAt: $now->modify('+15 minutes'))
            ->token;

        return $userId->toString();
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return TestResponse<Response>
     */
    protected function writeAs(string $token, string $path, string $ifMatch, array $body = [], string $method = 'POST'): TestResponse
    {
        $headers = $this->bearerWithIfMatch(token: $token, ifMatch: $ifMatch);

        return $this->json(
            $method,
            $path,
            $body,
            $headers,
        );
    }
}
