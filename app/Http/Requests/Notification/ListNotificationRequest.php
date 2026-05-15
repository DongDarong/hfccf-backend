<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['all', 'unread', 'read', 'dismissed'])],
            'type' => ['sometimes', 'nullable', 'string', Rule::in(['info', 'success', 'warning', 'error', 'system'])],
            'module' => ['sometimes', 'nullable', 'string', Rule::in(['global', 'english', 'preschool', 'scholarship', 'sport'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
