<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreSportTournamentRequest extends FormRequest
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
            'tournament_code' => $this->input('tournament_code', $this->input('tournamentCode')),
            'tournament_type' => $this->input('tournament_type', $this->input('tournamentType', 'league')),
            'starts_at' => $this->input('starts_at', $this->input('startsAt')),
            'ends_at' => $this->input('ends_at', $this->input('endsAt')),
        ]);
    }

    public function rules(): array
    {
        return [
            'tournament_code' => ['sometimes', 'nullable', 'string', 'max:32', 'unique:sport_tournaments,tournament_code'],
            'name' => ['required', 'string', 'max:191'],
            'season' => ['sometimes', 'nullable', 'string', 'max:64'],
            'tournament_type' => ['sometimes', 'nullable', 'in:league'],
            'status' => ['required', 'in:draft,active,completed,archived'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
