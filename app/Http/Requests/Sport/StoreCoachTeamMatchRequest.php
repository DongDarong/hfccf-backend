<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreCoachTeamMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return (bool) $user && in_array($user->role_code, ['superadmin', 'adminsport', 'coach'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'team_id' => $this->input('team_id', $this->input('teamId')),
            'opponent_team_id' => $this->input('opponent_team_id', $this->input('opponentTeamId')),
            'match_type' => $this->input('match_type', $this->input('matchType', 'friendly')),
            'scheduled_at' => $this->input('scheduled_at', $this->input('scheduledAt')),
        ]);
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:sport_teams,id'],
            'opponent_team_id' => ['required', 'integer', 'exists:sport_teams,id'],
            'match_type' => ['sometimes', 'nullable', 'in:friendly,training'],
            'competition_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'match_code' => ['sometimes', 'nullable', 'string', 'max:32', 'unique:sport_matches,match_code'],
            'venue' => ['sometimes', 'nullable', 'string', 'max:191'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
