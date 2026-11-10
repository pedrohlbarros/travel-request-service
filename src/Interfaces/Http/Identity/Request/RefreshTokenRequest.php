<?php

declare(strict_types=1);

namespace Interfaces\Http\Identity\Request;

use Illuminate\Foundation\Http\FormRequest;

final class RefreshTokenRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string'],
        ];
    }

    public function refreshToken(): string
    {
        return $this->string('refresh_token')
            ->toString();
    }
}
