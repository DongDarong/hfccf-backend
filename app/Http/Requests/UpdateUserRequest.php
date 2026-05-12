<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $userId = (string) $this->route('user');

        return [
            'firstName' => ['sometimes', 'required', 'string', 'max:100'],
            'lastName' => ['sometimes', 'required', 'string', 'max:100'],
            'username' => ['sometimes', 'nullable', 'string', 'max:191'],
            'email' => ['sometimes', 'required', 'email', 'max:191', 'unique:users,email,'.$userId.',id'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'role' => ['sometimes', 'required', 'string', 'max:32', 'exists:roles,code'],
            'departmentCode' => ['sometimes', 'nullable', 'string', 'max:32', 'exists:departments,code'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', 'in:active,pending,inactive,suspended'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'permissions' => ['sometimes', 'array', 'min:1'],
            'permissions.*' => ['string', 'max:64', 'exists:permissions,code'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
