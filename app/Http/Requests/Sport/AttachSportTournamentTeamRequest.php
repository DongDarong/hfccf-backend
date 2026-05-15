<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AttachSportTournamentTeamRequest extends FormRequest
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
            'team_id' => $this->input('team_id', $this->input('teamId')),
        ]);
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:sport_teams,id'],
        ];
    }
}
