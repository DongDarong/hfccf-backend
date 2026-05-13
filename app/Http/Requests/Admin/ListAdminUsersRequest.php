<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAdminUsersRequest extends FormRequest
{
    private const SORTABLE_COLUMNS = [
        'created_at',
        'first_name',
        'email',
        'role',
        'status',
    ];

    private const SORT_DIRECTIONS = [
        'asc',
        'desc',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role' => ['sometimes', 'nullable', 'string', Rule::exists('roles', 'code')],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['active', 'pending', 'inactive', 'suspended'])],
            'sort_by' => ['sometimes', 'nullable', 'string', Rule::in(self::SORTABLE_COLUMNS)],
            'sort_direction' => ['sometimes', 'nullable', 'string', Rule::in(self::SORT_DIRECTIONS)],
        ];
    }
}
