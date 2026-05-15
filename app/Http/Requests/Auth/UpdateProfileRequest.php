<?php

namespace App\Http\Requests\Auth;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->exists('first_name') || $this->exists('firstName')) {
            $payload['first_name'] = $this->input('first_name', $this->input('firstName'));
        }

        if ($this->exists('last_name') || $this->exists('lastName')) {
            $payload['last_name'] = $this->input('last_name', $this->input('lastName'));
        }

        if ($this->exists('username') || $this->exists('userName')) {
            $payload['username'] = $this->input('username', $this->input('userName'));
        }

        if ($this->exists('email')) {
            $payload['email'] = $this->input('email');
        }

        if ($this->exists('phone')) {
            $payload['phone'] = $this->input('phone');
        }

        if ($this->exists('bio')) {
            $payload['bio'] = $this->input('bio');
        }

        if ($this->exists('department_code') || $this->exists('department')) {
            $departmentValue = $this->input('department_code', $this->input('department'));

            if (is_string($departmentValue)) {
                $departmentValue = trim($departmentValue);
            }

            if (is_string($departmentValue) && $departmentValue !== '') {
                $department = Department::query()
                    ->where('code', $departmentValue)
                    ->orWhere('name', $departmentValue)
                    ->first();

                if ($department) {
                    $departmentValue = $department->code;
                }
            }

            $payload['department_code'] = $departmentValue;
        }

        if ($this->exists('remove_avatar')) {
            $payload['remove_avatar'] = filter_var($this->input('remove_avatar'), FILTER_VALIDATE_BOOL);
        }

        $this->merge($payload);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $userId = (string) $this->user()?->getKey();

        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'username' => ['sometimes', 'nullable', 'string', 'max:191'],
            'email' => ['sometimes', 'nullable', 'email', 'max:191', 'unique:users,email,'.$userId.',id'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'department_code' => ['sometimes', 'nullable', 'string', 'max:32', 'exists:departments,code'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
        ];
    }
}
