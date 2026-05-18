<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DrawTournamentGroupsRequest extends FormRequest
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
            'group_count' => $this->input('group_count', $this->input('groupCount')),
            'qualification_slots' => $this->input('qualification_slots', $this->input('qualificationSlots')),
            'assignments' => $this->input('assignments', []),
            'reset' => $this->boolean('reset', true),
        ]);
    }

    public function rules(): array
    {
        return [
            'group_count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:16'],
            'qualification_slots' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:8'],
            'assignments' => ['sometimes', 'nullable', 'array'],
            'assignments.*' => ['nullable'],
            'reset' => ['sometimes', 'boolean'],
        ];
    }
}
