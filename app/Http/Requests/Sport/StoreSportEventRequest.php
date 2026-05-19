<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreSportEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'event_type' => $this->input('event_type', $this->input('eventType')),
            'extra_time_minute' => $this->input('extra_time_minute', $this->input('extraTimeMinute')),
            'stoppage_minute' => $this->input('stoppage_minute', $this->input('stoppageMinute')),
            'squad_id' => $this->input('squad_id', $this->input('squadId')),
            'squad_player_id' => $this->input('squad_player_id', $this->input('squadPlayerId')),
            'related_squad_player_id' => $this->input('related_squad_player_id', $this->input('relatedSquadPlayerId')),
            'player_name' => $this->input('player_name', $this->input('playerName')),
            'player_name_snapshot' => $this->input('player_name_snapshot', $this->input('playerNameSnapshot')),
            'jersey_number_snapshot' => $this->input('jersey_number_snapshot', $this->input('jerseyNumberSnapshot')),
            'position_snapshot' => $this->input('position_snapshot', $this->input('positionSnapshot')),
            'period' => $this->input('period', $this->input('matchPeriod')),
            'side' => $this->input('side', $this->input('teamSide')),
            'metadata' => $this->input('metadata', null),
        ]);
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:sport_teams,id'],
            'player_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_players,id'],
            'player_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'squad_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_match_squads,id'],
            'squad_player_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_match_squad_players,id'],
            'related_squad_player_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_match_squad_players,id'],
            'event_type' => ['required', 'in:goal,assist,yellow_card,red_card,substitution,substitution_in,substitution_out,injury,penalty_goal,penalty_miss,penalty_missed,own_goal,extra_time_goal'],
            'minute' => ['required', 'integer', 'min:0', 'max:300'],
            'extra_time_minute' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:30'],
            'stoppage_minute' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:30'],
            'period' => ['sometimes', 'nullable', 'string', 'max:32'],
            'side' => ['sometimes', 'nullable', 'string', 'in:home,away'],
            'player_name_snapshot' => ['sometimes', 'nullable', 'string', 'max:191'],
            'jersey_number_snapshot' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999'],
            'position_snapshot' => ['sometimes', 'nullable', 'string', 'max:191'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
