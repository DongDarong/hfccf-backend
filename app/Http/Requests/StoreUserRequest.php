<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'username' => ['nullable', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', 'string', 'max:32', 'exists:roles,code'],
            'role_code' => ['sometimes', 'string', 'max:32', 'exists:roles,code'],
            'department_code' => ['nullable', 'string', 'max:32', 'exists:departments,code'],
            'bio' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,pending,inactive,suspended'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
