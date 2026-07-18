<?php

namespace App\Http\Requests\Sport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSportTrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role_code, ['superadmin', 'adminsport'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->input('status', 'scheduled'),
            'intensity' => $this->input('intensity', 'medium'),
        ]);
    }

    public function rules(): array
    {
        return [
            'session_code' => ['sometimes', 'nullable', 'string', 'max:32', 'unique:sport_training_sessions,session_code'],
            'team_id' => ['required', 'integer', 'exists:sport_teams,id'],
            'coach_user_id' => ['sometimes', 'nullable', 'string', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role_code', 'coach'))],
            'title' => ['required', 'string', 'max:191'],
            'training_type' => ['required', Rule::in(['technical', 'tactical', 'fitness', 'recovery', 'match_preparation'])],
            'focus' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'venue' => ['sometimes', 'nullable', 'string', 'max:191'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'intensity' => ['required', Rule::in(['low', 'medium', 'high'])],
            'status' => ['required', Rule::in(['scheduled', 'live', 'completed', 'postponed', 'cancelled'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
