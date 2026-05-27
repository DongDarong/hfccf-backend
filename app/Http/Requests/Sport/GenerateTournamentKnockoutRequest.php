<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class GenerateTournamentKnockoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return (bool) $user && in_array($user->role_code, ['superadmin', 'adminsport'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'replace' => $this->boolean('replace', true),
        ]);
    }

    public function rules(): array
    {
        return [
            'replace' => ['sometimes', 'boolean'],
        ];
    }
}
