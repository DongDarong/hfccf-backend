<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class GenerateTournamentFixturesRequest extends FormRequest
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
            'double_round_robin' => $this->boolean('double_round_robin', $this->boolean('doubleRoundRobin', false)),
            'replace' => $this->boolean('replace', true),
        ]);
    }

    public function rules(): array
    {
        return [
            'double_round_robin' => ['sometimes', 'boolean'],
            'replace' => ['sometimes', 'boolean'],
        ];
    }
}
