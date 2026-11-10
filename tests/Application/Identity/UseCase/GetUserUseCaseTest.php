<?php

declare(strict_types=1);

namespace Tests\Application\Identity\UseCase;

use Application\Identity\Output\UserOutput;
use Application\Identity\UseCase\GetUserUseCase;
use Domain\Identity\Exception\UserNotFound;
use Tests\Support\Identity\Fixture\IdentityMother as Mother;

final class GetUserUseCaseTest extends IdentityUseCaseTestCase
{
    public function test_ReturnsTheUser(): void
    {
        $user = Mother::user();
        $this->users->add(user: $user);

        $found = new GetUserUseCase(users: $this->users)->execute(userId: Mother::USER_ID);

        self::assertEquals(UserOutput::fromEntity(user: $user), $found);
    }

    public function test_FailsForUnknownUser(): void
    {
        $this->expectException(UserNotFound::class);

        new GetUserUseCase(users: $this->users)->execute(userId: Mother::USER_ID);
    }
}
