<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use App\Support\SportMembershipStatus;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRosterMembershipRequest extends FormRequest
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
            'team_id' => $this->input('team_id', $this->route('team')),
            'player_id' => $this->input('player_id', $this->input('playerId')),
            'membership_status' => $this->input('membership_status', $this->input('status', SportMembershipStatus::ACTIVE)),
        ]);
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:sport_teams,id'],
            'player_id' => ['required', 'integer', 'exists:sport_players,id'],
            'membership_status' => ['sometimes', 'nullable', 'in:active,loaned,inactive,suspended,released,expired'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
