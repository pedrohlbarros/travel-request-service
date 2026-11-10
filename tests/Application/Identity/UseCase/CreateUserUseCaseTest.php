<?php

declare(strict_types=1);

namespace Tests\Application\Identity\UseCase;

use Application\Identity\Input\CreateUserInput;
use Application\Identity\Output\UserOutput;
use Application\Identity\UseCase\CreateUserUseCase;
use Domain\Identity\Enum\Role;
use Domain\Identity\Exception\EmailAlreadyRegistered;
use Domain\Identity\Exception\InvalidEmail;
use Domain\Identity\Exception\InvalidPassword;
use Domain\Identity\Exception\InvalidUserName;
use Tests\Support\Identity\Fixture\IdentityMother as Mother;
use Tests\Support\Identity\Port\FakePasswordHasher;
use Tests\Support\Shared\Port\SequentialIdentifierGenerator;

final class CreateUserUseCaseTest extends IdentityUseCaseTestCase
{
    private CreateUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new CreateUserUseCase(
            users: $this->users,
            passwordHasher: $this->passwordHasher,
            identifiers: $this->identifiers,
            clock: $this->clock,
        );
    }

    public function test_CreatesUserWithHashedPassword(): void
    {
        $input = new CreateUserInput(
            name: ' ' . Mother::NAME . ' ',
            email: 'Luffy@Example.com',
            password: Mother::PASSWORD,
            role: Role::User,
        );

        $created = $this->useCase->execute(input: $input);

        [$stored] = $this->users->all();

        self::assertEquals(UserOutput::fromEntity(user: $stored), $created);
        self::assertSame(SequentialIdentifierGenerator::nth(sequence: 1), $created->id);
        self::assertSame(Mother::NAME, $created->name);
        self::assertSame(Mother::EMAIL, $created->email);
        self::assertSame(Role::User, $created->role);
        self::assertEquals(Mother::moment(), $created->createdAt);
        self::assertSame(FakePasswordHasher::HASH_PREFIX . Mother::PASSWORD, $stored->passwordHash->value);
    }

    public function test_CreatesAdminWhenAsked(): void
    {
        $created = $this->useCase->execute(input: new CreateUserInput(
            name: Mother::NAME,
            email: Mother::ADMIN_EMAIL,
            password: Mother::PASSWORD,
            role: Role::Admin,
        ));

        self::assertSame(Role::Admin, $created->role);
    }

    public function test_RejectsEmailAlreadyRegisteredInAnyCaseBeforeHashing(): void
    {
        $this->users->add(user: Mother::user());
        $sameEmailInUppercase = new CreateUserInput(
            name: Mother::NAME,
            email: strtoupper(Mother::EMAIL),
            password: Mother::PASSWORD,
            role: Role::User,
        );

        try {
            $this->useCase->execute(input: $sameEmailInUppercase);
            self::fail('A registered email must be rejected.');
        } catch (EmailAlreadyRegistered) {
        }

        self::assertCount(1, $this->users->all());
        self::assertSame(0, $this->passwordHasher->hashings);
    }

    public function test_RejectsInvalidName(): void
    {
        $this->expectException(InvalidUserName::class);

        $this->useCase->execute(input: new CreateUserInput(
            name: 'L',
            email: Mother::EMAIL,
            password: Mother::PASSWORD,
            role: Role::User,
        ));
    }

    public function test_RejectsInvalidEmail(): void
    {
        $this->expectException(InvalidEmail::class);

        $this->useCase->execute(input: new CreateUserInput(
            name: Mother::NAME,
            email: 'not-an-email',
            password: Mother::PASSWORD,
            role: Role::User,
        ));
    }

    public function test_RejectsPasswordOutsideThePolicy(): void
    {
        $this->expectException(InvalidPassword::class);

        $this->useCase->execute(input: new CreateUserInput(
            name: Mother::NAME,
            email: Mother::EMAIL,
            password: 'short',
            role: Role::User,
        ));
    }
}
