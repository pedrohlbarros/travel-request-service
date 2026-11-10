<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Identity\Persistence;

use Domain\Identity\Enum\Role;
use Domain\Identity\Exception\EmailAlreadyRegistered;
use Domain\Identity\Exception\UserNotFound;
use Domain\Identity\ValueObject\Email;
use Domain\Identity\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Infrastructure\Identity\Persistence\EloquentUserRepository;
use Tests\Support\Identity\Fixture\IdentityMother as Mother;
use Tests\Support\LaravelTestCase;

final class EloquentUserRepositoryTest extends LaravelTestCase
{
    use RefreshDatabase;

    private EloquentUserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new EloquentUserRepository();
    }

    public function test_StoresAndReadsBackEveryField(): void
    {
        $admin = Mother::admin();

        $this->users->add(user: $admin);

        $foundById = $this->users->get(id: $admin->id);
        $foundByEmail = $this->users->findByEmail(email: $admin->email);

        self::assertEquals($admin, $foundById);
        self::assertEquals($admin, $foundByEmail);
    }

    public function test_StoresTheIdAsSixteenBytes(): void
    {
        $this->users->add(user: Mother::user());

        $storedIdLength = DB::table('users')
            ->selectRaw('LENGTH(id) AS id_length')
            ->value('id_length');

        self::assertEquals(16, $storedIdLength);
    }

    public function test_FindByEmailIsNullForUnknownEmail(): void
    {
        $unknownEmail = Email::fromString(address: Mother::EMAIL);

        $found = $this->users->findByEmail(email: $unknownEmail);

        self::assertNull($found);
    }

    public function test_GetFailsForUnknownUser(): void
    {
        $this->expectException(UserNotFound::class);

        $this->users->get(id: UserId::fromString(id: Mother::USER_ID));
    }

    public function test_DuplicateEmailCaughtByTheUniqueIndex(): void
    {
        $this->users->add(user: Mother::user());

        $this->expectException(EmailAlreadyRegistered::class);

        $this->users->add(user: Mother::user(id: Mother::ADMIN_ID, email: Mother::EMAIL, role: Role::Admin));
    }
}
