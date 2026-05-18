<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSportEventRequest extends FormRequest
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
            'event_type' => $this->input('event_type', $this->input('eventType')),
            'extra_time_minute' => $this->input('extra_time_minute', $this->input('extraTimeMinute')),
            'player_name' => $this->input('player_name', $this->input('playerName')),
            'metadata' => $this->input('metadata', null),
        ]);
    }

    public function rules(): array
    {
        return [
            'team_id' => ['sometimes', 'required', 'integer', 'exists:sport_teams,id'],
            'player_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_players,id'],
            'player_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'event_type' => ['sometimes', 'required', 'in:goal,own_goal,yellow_card,red_card,substitution,penalty_goal,penalty_missed'],
            'minute' => ['sometimes', 'required', 'integer', 'min:0', 'max:300'],
            'extra_time_minute' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:30'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
