<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSportMatchRequest extends FormRequest
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
            'home_team' => $this->input('home_team', $this->input('homeTeam')),
            'away_team' => $this->input('away_team', $this->input('awayTeam')),
            'tournament_id' => $this->input('tournament_id', $this->input('tournamentId')),
            'competition_type' => $this->input('competition_type', $this->input('competitionType')),
            'tournament_name' => $this->input('tournament_name', $this->input('tournament')),
            'scheduled_at' => $this->input('scheduled_at', $this->input('date_time', $this->input('dateTime'))),
            'current_period' => $this->input('current_period', $this->input('currentPeriod')),
            'notes' => $this->input('notes', $this->input('report')),
        ]);
    }

    public function rules(): array
    {
        $matchId = (string) $this->route('id');

        return [
            'match_code' => ['sometimes', 'nullable', 'string', 'max:32', 'unique:sport_matches,match_code,'.$matchId.',id'],
            'home_team' => ['sometimes', 'required', 'string', 'max:191'],
            'away_team' => ['sometimes', 'required', 'string', 'max:191'],
            'tournament_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_tournaments,id'],
            'competition_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'tournament_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'venue' => ['sometimes', 'nullable', 'string', 'max:191'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'required', 'in:draft,scheduled,live,halftime,completed,postponed,cancelled'],
            'current_period' => ['sometimes', 'nullable', 'string', 'max:64'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
