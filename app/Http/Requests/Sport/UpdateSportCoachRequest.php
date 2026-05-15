<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSportCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return (bool) $user && in_array($user->role_code, ['superadmin', 'adminsport'], true);
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name', $this->input('full_name', '')));
        [$firstName, $lastName] = $this->splitName($name);

        $this->merge([
            'first_name' => $this->input('first_name', $firstName),
            'last_name' => $this->input('last_name', $lastName),
            'username' => $this->input('username', $name),
            'status' => $this->input('status', 'active'),
            'password_confirmation' => $this->input('password_confirmation', $this->input('confirmPassword')),
        ]);
    }

    public function rules(): array
    {
        $coachId = (string) $this->route('id');

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'username' => ['sometimes', 'nullable', 'string', 'max:191'],
            'email' => ['sometimes', 'required', 'email', 'max:191', 'unique:users,email,'.$coachId.',id'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'status' => ['sometimes', 'required', 'in:active,pending,inactive,suspended'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        if ($name === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $name, 2) ?: [$name, ''];

        return [
            trim((string) ($parts[0] ?? '')),
            trim((string) ($parts[1] ?? '')),
        ];
    }
}

