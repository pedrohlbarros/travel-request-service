<?php

declare(strict_types=1);

namespace Interfaces\Http\Identity\Request;

use Application\Identity\Input\CreateUserInput;
use Domain\Identity\Enum\Role;
use Domain\Identity\ValueObject\Email;
use Domain\Identity\ValueObject\PlainPassword;
use Domain\Identity\ValueObject\UserName;
use Illuminate\Foundation\Http\FormRequest;

final class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:' . UserName::SHORTEST_NAME, 'max:' . UserName::LONGEST_NAME],
            'email' => ['required', 'string', 'email', 'max:' . Email::LONGEST_ADDRESS],
            'password' => ['required', 'string', 'min:' . PlainPassword::SHORTEST_PASSWORD, 'max:' . PlainPassword::LONGEST_PASSWORD],
        ];
    }

    /**
     * Registration always creates a regular user; admins only come from the console.
     */
    public function toInput(): CreateUserInput
    {
        return new CreateUserInput(
            name: $this->string('name')
                ->toString(),
            email: $this->string('email')
                ->toString(),
            password: $this->string('password')
                ->toString(),
            role: Role::User,
        );
    }
}
