<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => $this->input('first_name', $this->input('firstName')),
            'last_name' => $this->input('last_name', $this->input('lastName')),
            'role' => $this->input('role', $this->input('role_code')),
            'role_code' => $this->input('role_code', $this->input('role')),
            'department_code' => $this->input('department_code', $this->input('departmentCode')),
            'confirm_password' => $this->input('confirm_password', $this->input('confirmPassword')),
        ]);
    }

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
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'username' => ['sometimes', 'nullable', 'string', 'max:191', Rule::unique('users', 'username')->ignore($userId, 'id')],
            'email' => ['sometimes', 'required', 'email', 'max:191', 'unique:users,email,'.$userId.',id'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'role' => ['sometimes', 'required', 'string', 'max:32', 'exists:roles,code'],
            'role_code' => ['sometimes', 'string', 'max:32', 'exists:roles,code'],
            'department_code' => ['sometimes', 'nullable', 'string', 'max:32', 'exists:departments,code'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', 'in:active,pending,inactive,suspended'],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
        ];
    }
}
