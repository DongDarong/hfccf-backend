<?php

namespace App\Http\Requests\Sport;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamRosterMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return (bool) $user && in_array($user->role_code, ['superadmin', 'adminsport', 'coach'], true);
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', 'in:active,loaned,inactive,suspended,released,expired'],
            'suspension_until' => ['sometimes', 'nullable', 'date'],
            'injury_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
