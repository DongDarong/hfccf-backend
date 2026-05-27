<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTournamentMatchEventRequest extends FormRequest
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
            'player_name' => $this->input('player_name', $this->input('playerName')),
            'assist_player_name' => $this->input('assist_player_name', $this->input('assistPlayerName')),
            'player_in_name' => $this->input('player_in_name', $this->input('playerInName')),
            'player_out_name' => $this->input('player_out_name', $this->input('playerOutName')),
            'assist_player_id' => $this->input('assist_player_id', $this->input('assistPlayerId')),
            'player_in_id' => $this->input('player_in_id', $this->input('playerInId')),
            'player_out_id' => $this->input('player_out_id', $this->input('playerOutId')),
            'stoppage_minute' => $this->input('stoppage_minute', $this->input('stoppageMinute')),
            'side' => $this->input('side'),
            'description' => $this->input('description'),
            'metadata' => $this->input('metadata', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:sport_teams,id'],
            'player_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_players,id'],
            'player_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'assist_player_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_players,id'],
            'assist_player_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'player_in_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_players,id'],
            'player_in_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'player_out_id' => ['sometimes', 'nullable', 'integer', 'exists:sport_players,id'],
            'player_out_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'event_type' => [
                'required',
                'string',
                Rule::in(['goal', 'assist', 'yellow_card', 'red_card', 'own_goal', 'penalty_goal', 'penalty_miss', 'substitution', 'injury', 'var_review', 'note']),
            ],
            'minute' => ['required', 'integer', 'min:0', 'max:120'],
            'stoppage_minute' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'],
            'side' => ['sometimes', 'nullable', Rule::in(['home', 'away'])],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            $eventType = strtolower(trim((string) $this->input('event_type')));
            $playerId = $this->input('player_id');
            $playerName = trim((string) $this->input('player_name'));
            $assistPlayerId = $this->input('assist_player_id');
            $assistPlayerName = trim((string) $this->input('assist_player_name'));

            if (in_array($eventType, ['goal', 'assist', 'own_goal', 'yellow_card', 'red_card', 'penalty_goal', 'penalty_miss', 'substitution', 'injury', 'var_review'], true) && empty($playerId) && $playerName === '') {
                $validator->errors()->add('player_id', 'The player field is required for this event type.');
            }

            if (($assistPlayerId && $playerId && (string) $assistPlayerId === (string) $playerId) || ($assistPlayerName !== '' && $playerName !== '' && mb_strtolower($assistPlayerName) === mb_strtolower($playerName))) {
                $validator->errors()->add('assist_player_id', 'The assist player must be different from the scorer.');
            }
        });
    }
}
