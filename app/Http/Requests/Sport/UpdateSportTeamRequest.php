<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSportTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return (bool) $user && in_array($user->role_code, ['superadmin', 'adminsport'], true);
    }

    protected function prepareForValidation(): void
    {
        $payload = [
            'team_code' => $this->input('team_code', $this->input('teamCode')),
            'coach_display_name' => $this->input('coach_display_name', $this->input('coach')),
            'captain_name' => $this->input('captain_name', $this->input('captain')),
            'players_count' => $this->input('players_count', $this->input('players')),
            'matches_count' => $this->input('matches_count', $this->input('matches')),
            'remove_logo' => $this->boolean('remove_logo'),
        ];

        $this->merge($payload);
    }

    public function rules(): array
    {
        return [
            'team_code' => ['sometimes', 'nullable', 'string', 'max:32', 'unique:sport_teams,team_code,'.$this->route('id').',id'],
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'coach_user_id' => ['sometimes', 'nullable', 'string', 'max:32', 'exists:users,id'],
            'coach' => ['sometimes', 'nullable', 'string', 'max:191'],
            'coach_display_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'division' => ['sometimes', 'nullable', 'string', 'max:100'],
            'captain' => ['sometimes', 'nullable', 'string', 'max:191'],
            'captain_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'players_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'matches_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'wins' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'draws' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'losses' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'points' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'venue' => ['sometimes', 'nullable', 'string', 'max:191'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'required', 'in:active,pending,inactive,suspended'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
