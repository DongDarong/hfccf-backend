<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTournamentKnockoutResultRequest extends FormRequest
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
            'home_score' => $this->input('home_score', $this->input('homeScore')),
            'away_score' => $this->input('away_score', $this->input('awayScore')),
            'extra_time_home_score' => $this->input('extra_time_home_score', $this->input('extraTimeHomeScore')),
            'extra_time_away_score' => $this->input('extra_time_away_score', $this->input('extraTimeAwayScore')),
            'penalty_home_score' => $this->input('penalty_home_score', $this->input('penaltyHomeScore')),
            'penalty_away_score' => $this->input('penalty_away_score', $this->input('penaltyAwayScore')),
            'winner_team_id' => $this->input('winner_team_id', $this->input('winnerTeamId')),
            'status' => $this->input('status', 'completed'),
            'current_period' => $this->input('current_period', $this->input('currentPeriod')),
        ]);
    }

    public function rules(): array
    {
        return [
            'home_score' => ['sometimes', 'required', 'integer', 'min:0', 'max:50'],
            'away_score' => ['sometimes', 'required', 'integer', 'min:0', 'max:50'],
            'extra_time_home_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'extra_time_away_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'penalty_home_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'penalty_away_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'winner_team_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_teams,id'],
            'status' => ['sometimes', 'nullable', Rule::in(['scheduled', 'live', 'halftime', 'completed'])],
            'current_period' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
