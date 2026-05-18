<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreCoachTeamAssignmentRequest extends FormRequest
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
            'coach_user_id' => $this->input('coach_user_id', $this->input('coachUserId')),
            'team_id' => $this->input('team_id', $this->input('teamId')),
            'status' => $this->input('status', 'active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'coach_user_id' => ['required', 'string', 'max:32', 'exists:users,id'],
            'team_id' => ['required', 'integer', 'exists:sport_teams,id'],
            'status' => ['sometimes', 'nullable', 'in:active,inactive'],
        ];
    }
}
