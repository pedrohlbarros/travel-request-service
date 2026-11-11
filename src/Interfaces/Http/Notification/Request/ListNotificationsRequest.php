<?php

declare(strict_types=1);

namespace Interfaces\Http\Notification\Request;

use Application\Notification\Input\ListNotificationsInput;
use Illuminate\Foundation\Http\FormRequest;

final class ListNotificationsRequest extends FormRequest
{
    public const int DEFAULT_PER_PAGE = 20;

    public const int MAX_PER_PAGE = 100;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'unread' => ['sometimes', 'in:true,false,1,0'],
            'cursor' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'between:1,' . self::MAX_PER_PAGE],
        ];
    }

    public function toInput(string $recipientId): ListNotificationsInput
    {
        $perPage = $this->integer('per_page', self::DEFAULT_PER_PAGE);
        $cursor = null;

        if ($this->has('cursor')) {
            $cursor = $this->string('cursor')
                ->toString();
        }

        return new ListNotificationsInput(
            recipientId: $recipientId,
            isUnreadOnly: $this->boolean('unread'),
            cursor: $cursor,
            perPage: max(1, $perPage),
        );
    }
}
