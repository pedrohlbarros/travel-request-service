<?php

declare(strict_types=1);

namespace Interfaces\Http\Identity\Request;

use Application\Identity\Input\LoginInput;
use Domain\Identity\ValueObject\Email;
use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:' . Email::LONGEST_ADDRESS],
            'password' => ['required', 'string'],
        ];
    }

    public function toInput(): LoginInput
    {
        return new LoginInput(
            email: $this->string('email')
                ->toString(),
            password: $this->string('password')
                ->toString(),
        );
    }
}
