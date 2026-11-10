<?php

declare(strict_types=1);

namespace Interfaces\Console\Identity;

use Application\Identity\Input\CreateUserInput;
use Application\Identity\UseCase\CreateUserUseCase;
use Domain\Identity\Enum\Role;
use Domain\Shared\Exception\BusinessRuleViolation;
use Illuminate\Console\Command;

/**
 * Base for the user and admin commands; only the role differs, hence abstract instead of final.
 */
abstract class CreateUserCommand extends Command
{
    abstract protected function role(): Role;

    public function handle(CreateUserUseCase $createUser): int
    {
        $newUser = new CreateUserInput(
            name: $this->optionOrAsk(option: 'name', question: 'Name'),
            email: $this->optionOrAsk(option: 'email', question: 'Email'),
            password: $this->optionOrAsk(option: 'password', question: 'Password', isSecret: true),
            role: $this->role(),
        );

        try {
            $user = $createUser->execute(input: $newUser);
        } catch (BusinessRuleViolation $violation) {
            $this->components->error($violation->getMessage());

            return self::FAILURE;
        }

        $this->components->info(\sprintf(
            'Created %s %s (%s).',
            $user->role->value,
            $user->email,
            $user->id,
        ));

        return self::SUCCESS;
    }

    private function optionOrAsk(string $option, string $question, bool $isSecret = false): string
    {
        $value = $this->option($option);

        if (\is_string($value) && $value !== '') {
            return $value;
        }

        $answer = $isSecret ? $this->secret($question) : $this->ask($question);

        return \is_string($answer) ? $answer : '';
    }
}
