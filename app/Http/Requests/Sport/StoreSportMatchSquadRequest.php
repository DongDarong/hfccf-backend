<?php

namespace App\Http\Requests\Sport;

use App\Support\SportMatchSquadPlayerRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSportMatchSquadRequest extends FormRequest
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
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'players' => ['sometimes', 'array'],
            'players.*.player_id' => ['required', 'integer', 'exists:sport_players,id'],
            'players.*.role' => ['sometimes', 'string', Rule::in(SportMatchSquadPlayerRole::values())],
        ];
    }
}
