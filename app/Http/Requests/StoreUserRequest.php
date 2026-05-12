<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
        return [
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'username' => ['nullable', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', 'string', 'max:32', 'exists:roles,code'],
            'departmentCode' => ['nullable', 'string', 'max:32', 'exists:departments,code'],
            'bio' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,pending,inactive,suspended'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'permissions' => ['nullable', 'array', 'min:1'],
            'permissions.*' => ['string', 'max:64', 'exists:permissions,code'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
