<?php

namespace App\Http\Requests\Sport;

use App\Models\SportTrainingSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSportTrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role_code, ['superadmin', 'adminsport'], true);
    }

    protected function prepareForValidation(): void
    {
        $session = $this->route('id') ? SportTrainingSession::find($this->route('id')) : null;
        $this->merge([
            'starts_at' => $this->input('starts_at', $session?->starts_at?->toDateTimeString()),
            'ends_at' => $this->input('ends_at', $session?->ends_at?->toDateTimeString()),
        ]);
    }

    public function rules(): array
    {
        return [
            'session_code' => ['sometimes', 'nullable', 'string', 'max:32', Rule::unique('sport_training_sessions', 'session_code')->ignore($this->route('id'))],
            'team_id' => ['sometimes', 'required', 'integer', 'exists:sport_teams,id'],
            'coach_user_id' => ['sometimes', 'nullable', 'string', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role_code', 'coach'))],
            'title' => ['sometimes', 'required', 'string', 'max:191'],
            'training_type' => ['sometimes', 'required', Rule::in(['technical', 'tactical', 'fitness', 'recovery', 'match_preparation'])],
            'focus' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'venue' => ['sometimes', 'nullable', 'string', 'max:191'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date', 'after:starts_at'],
            'intensity' => ['sometimes', 'required', Rule::in(['low', 'medium', 'high'])],
            'status' => ['sometimes', 'required', Rule::in(['scheduled', 'live', 'completed', 'postponed', 'cancelled'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
