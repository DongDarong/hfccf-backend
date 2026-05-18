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
        $payload = [];

        if ($this->exists('home_team') || $this->exists('homeTeam')) {
            $payload['home_team'] = $this->input('home_team', $this->input('homeTeam'));
        }

        if ($this->exists('away_team') || $this->exists('awayTeam')) {
            $payload['away_team'] = $this->input('away_team', $this->input('awayTeam'));
        }

        if ($this->exists('tournament_id') || $this->exists('tournamentId')) {
            $payload['tournament_id'] = $this->input('tournament_id', $this->input('tournamentId'));
        }

        if ($this->exists('competition_type') || $this->exists('competitionType')) {
            $payload['competition_type'] = $this->input('competition_type', $this->input('competitionType'));
        }

        if ($this->exists('tournament_name') || $this->exists('tournament')) {
            $payload['tournament_name'] = $this->input('tournament_name', $this->input('tournament'));
        }

        if ($this->exists('scheduled_at') || $this->exists('date_time') || $this->exists('dateTime')) {
            $payload['scheduled_at'] = $this->input('scheduled_at', $this->input('date_time', $this->input('dateTime')));
        }

        if ($this->exists('current_period') || $this->exists('currentPeriod')) {
            $payload['current_period'] = $this->input('current_period', $this->input('currentPeriod'));
        }

        if ($this->exists('notes') || $this->exists('report')) {
            $payload['notes'] = $this->input('notes', $this->input('report'));
        }

        $this->merge($payload);
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
            'match_type' => ['sometimes', 'nullable', 'in:training,friendly,tournament'],
            'tournament_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'venue' => ['sometimes', 'nullable', 'string', 'max:191'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'required', 'in:draft,scheduled,live,halftime,completed,postponed,cancelled'],
            'approval_status' => ['sometimes', 'nullable', 'in:pending,approved,rejected'],
            'current_period' => ['sometimes', 'nullable', 'string', 'max:64'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
